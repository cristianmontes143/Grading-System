<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Grading\GradebookService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentWorkflowAndExportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $adviser;
    private Teacher $mathTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();
        $this->adviser = $this->makeTeacher('EMP-AW1', 'Lina', 'Vega', 'lina.adv@school.local', Role::ADVISER);
        $this->mathTeacher = $this->makeTeacher('EMP-AW2', 'Ramon', 'Torres', 'ramon.math@school.local', Role::SUBJECT_TEACHER);
    }

    private function makeTeacher(string $empNo, string $first, string $last, string $email, string $roleName): Teacher
    {
        $user = User::query()->create([
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail()->id);

        return Teacher::query()->create([
            'user_id' => $user->id,
            'employee_number' => $empNo,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
        ]);
    }

    public function test_teacher_can_edit_assessment_with_max_score_guard_and_clone_assessments_across_sections(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy);
        $t1 = $sy->gradingPeriods()->where('code', 'T1')->firstOrFail();
        $t1->update(['status' => GradingPeriod::STATUS_OPEN]);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);

        $secA = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Section A',
            'capacity' => 40,
        ]);
        $secB = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Section B',
            'capacity' => 40,
        ]);

        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();

        $taA = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $secA->id,
            'subject_id' => $math->id,
            'teacher_id' => $this->mathTeacher->id,
            'is_active' => true,
        ]);
        $taB = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $secB->id,
            'subject_id' => $math->id,
            'teacher_id' => $this->mathTeacher->id,
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-3001',
            'lrn' => '100000003001',
            'first_name' => 'Paolo',
            'last_name' => 'Reyes',
            'gender' => 'Male',
            'date_of_birth' => '2013-06-05',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $enrA = app(EnrollmentService::class)->enrollStudent($student, $secA);

        $config = app(GradebookService::class)->resolveConfiguration($taA);
        $wwId = app(GradebookService::class)->getLeafComponents($config)->firstWhere('code', 'WW')['id'];
        $ptId = app(GradebookService::class)->getLeafComponents($config)->firstWhere('code', 'PT')['id'];

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.assessments.store', [$taA, $t1]), [
                'grading_component_id' => $wwId,
                'assessment_type' => 'quiz',
                'title' => 'Quiz 1',
                'max_score' => 20,
            ])
            ->assertRedirect();

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.assessments.store', [$taA, $t1]), [
                'grading_component_id' => $ptId,
                'assessment_type' => 'performance_task',
                'title' => 'Portfolio',
                'max_score' => 50,
            ])
            ->assertRedirect();

        $quiz = Assessment::query()->where('teacher_assignment_id', $taA->id)->where('title', 'Quiz 1')->firstOrFail();

        // Enter score 18/20
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$taA, $t1]), [
                'scores' => [$enrA->id => [$quiz->id => 18]],
            ])
            ->assertRedirect();

        // Reject lowering max_score below existing student score (15 < 18)
        $this->actingAs($this->mathTeacher->user)
            ->put(route('teacher.gradebook.assessments.update', [$taA, $t1, $quiz]), [
                'title' => 'Quiz 1 Revised',
                'assessment_type' => 'quiz',
                'max_score' => 15,
            ])
            ->assertSessionHasErrors('max_score');

        // Raising max_score to 25 succeeds, updates title, recalculates grade, and writes audit log
        $this->actingAs($this->mathTeacher->user)
            ->put(route('teacher.gradebook.assessments.update', [$taA, $t1, $quiz]), [
                'title' => 'Quiz 1 Revised',
                'assessment_type' => 'written_activity',
                'max_score' => 25,
            ])
            ->assertRedirect();

        $this->assertSame('Quiz 1 Revised', $quiz->fresh()->title);
        $this->assertSame(25.0, (float) $quiz->fresh()->max_score);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'assessment.updated']);

        $qg = QuarterlyGrade::query()->where('student_enrollment_id', $enrA->id)->where('grading_period_id', $t1->id)->firstOrFail();
        // WW = 18/25 = 72% * 20% = 14.4
        $this->assertSame(14.4, (float) $qg->initial_grade);

        // Clone assessments from Section A into Section B (copies both columns, skips duplicates on repeat)
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.assessments.clone', [$taB, $t1]), [
                'source_assignment_id' => $taA->id,
                'source_period_id' => $t1->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, Assessment::query()->where('teacher_assignment_id', $taB->id)->where('grading_period_id', $t1->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'assessment.cloned']);

        // Repeating clone does not duplicate existing assessments
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.assessments.clone', [$taB, $t1]), [
                'source_assignment_id' => $taA->id,
                'source_period_id' => $t1->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, Assessment::query()->where('teacher_assignment_id', $taB->id)->where('grading_period_id', $t1->id)->count());

        // Adviser can export Section Attendance Summary (.xlsx); subject teacher cannot
        AttendanceRecord::query()->create([
            'student_enrollment_id' => $enrA->id,
            'year_number' => 2026,
            'month_number' => 7,
            'school_days' => 22,
            'days_present' => 21,
            'days_absent' => 1,
            'days_tardy' => 0,
            'recorded_by' => $this->adviser->user_id,
        ]);

        $this->actingAs($this->adviser->user)
            ->get(route('adviser.records.export-attendance', $secA))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->mathTeacher->user)
            ->get(route('adviser.records.export-attendance', $secA))
            ->assertForbidden();

        // Admin can export Faculty & Teaching Loads (.xlsx); subject teacher cannot
        $this->actingAs($this->admin)
            ->get(route('admin.teachers.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->mathTeacher->user)
            ->get(route('admin.teachers.export'))
            ->assertForbidden();
    }
}
