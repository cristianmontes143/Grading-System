<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentIntervention;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InterventionsAndBulkAdminOpsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $adviser;
    private Teacher $subjectTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();
        $this->adviser = $this->makeTeacher('EMP-IV1', 'Maya', 'Cruz', 'maya.adv@school.local', Role::ADVISER);
        $this->subjectTeacher = $this->makeTeacher('EMP-IV2', 'Leo', 'Pascual', 'leo.sub@school.local', Role::SUBJECT_TEACHER);
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

    public function test_adviser_can_log_and_resolve_student_interventions_while_outsiders_are_blocked(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);

        $section = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Sapphire',
            'capacity' => 40,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-5501',
            'lrn' => '100000005501',
            'first_name' => 'Ethan',
            'last_name' => 'Morales',
            'gender' => 'Male',
            'date_of_birth' => '2013-01-18',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $enr = app(EnrollmentService::class)->enrollStudent($student, $section);
        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();

        // Adviser logs an Academic Remediation note
        $this->actingAs($this->adviser->user)
            ->post(route('adviser.students.interventions.store', $enr), [
                'category' => StudentIntervention::CATEGORY_REMEDIATION,
                'subject_id' => $math->id,
                'notes' => 'Parent conference conducted; learner needs extra practice on fractions.',
                'action_plan' => 'Attend weekly Math tutoring.',
                'follow_up_date' => '2026-12-10',
            ])
            ->assertRedirect();

        $intervention = StudentIntervention::query()->where('student_enrollment_id', $enr->id)->firstOrFail();
        $this->assertSame(StudentIntervention::STATUS_OPEN, $intervention->status);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'student_intervention.created']);

        // Adviser sees the logged note on Student Summary and marks it resolved
        $this->actingAs($this->adviser->user)
            ->get(route('adviser.students.summary', $enr))
            ->assertOk()
            ->assertSee('Parent conference conducted');

        $this->actingAs($this->adviser->user)
            ->post(route('adviser.students.interventions.toggle', [$enr, $intervention]))
            ->assertRedirect();

        $this->assertSame(StudentIntervention::STATUS_RESOLVED, $intervention->fresh()->status);
        $this->assertNotNull($intervention->fresh()->resolved_at);

        // Non-adviser subject teacher cannot log interventions for another section's advisee
        $this->actingAs($this->subjectTeacher->user)
            ->post(route('adviser.students.interventions.store', $enr), [
                'category' => StudentIntervention::CATEGORY_COUNSELING,
                'notes' => 'Unauthorized note.',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_bulk_approve_submitted_grades_and_clone_teacher_assignments_across_school_years(): void
    {
        $sy1 = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy1);
        $t1 = $sy1->gradingPeriods()->where('code', 'T1')->firstOrFail();
        $t1->update(['status' => GradingPeriod::STATUS_OPEN]);

        $sy2 = SchoolYear::query()->create([
            'name' => '2027-2028',
            'start_date' => '2027-06-14',
            'end_date' => '2028-03-31',
            'term_structure' => 'trimester',
            'is_active' => false,
        ]);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy1->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy2->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);

        $sec1 = Section::query()->create([
            'school_year_id' => $sy1->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Topaz',
            'capacity' => 45,
        ]);
        $sec2 = Section::query()->create([
            'school_year_id' => $sy2->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => null,
            'name' => 'Topaz',
            'capacity' => 45,
        ]);

        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();
        $eng = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'ENG7')->firstOrFail();

        $ta1 = TeacherAssignment::query()->create([
            'school_year_id' => $sy1->id,
            'section_id' => $sec1->id,
            'subject_id' => $math->id,
            'teacher_id' => $this->subjectTeacher->id,
            'schedule_summary' => 'MWF 9:00 AM',
            'is_active' => true,
        ]);
        $ta2 = TeacherAssignment::query()->create([
            'school_year_id' => $sy1->id,
            'section_id' => $sec1->id,
            'subject_id' => $eng->id,
            'teacher_id' => $this->adviser->id,
            'is_active' => true,
        ]);

        $sub1 = GradeSubmission::query()->create([
            'teacher_assignment_id' => $ta1->id,
            'grading_period_id' => $t1->id,
            'status' => GradeSubmission::STATUS_SUBMITTED,
            'total_students' => 1,
            'graded_students' => 1,
            'missing_scores_count' => 0,
            'submitted_by' => $this->subjectTeacher->user_id,
            'submitted_at' => now(),
        ]);
        $sub2 = GradeSubmission::query()->create([
            'teacher_assignment_id' => $ta2->id,
            'grading_period_id' => $t1->id,
            'status' => GradeSubmission::STATUS_SUBMITTED,
            'total_students' => 1,
            'graded_students' => 1,
            'missing_scores_count' => 0,
            'submitted_by' => $this->adviser->user_id,
            'submitted_at' => now(),
        ]);

        // Bulk approve all submitted classes for T1
        $this->actingAs($this->admin)
            ->post(route('admin.submissions.approve-all', $t1))
            ->assertRedirect();

        $this->assertSame(GradeSubmission::STATUS_APPROVED, $sub1->fresh()->status);
        $this->assertSame(GradeSubmission::STATUS_APPROVED, $sub2->fresh()->status);

        // Clone Advisers & Subject Teachers from SY1 -> SY2
        $this->actingAs($this->admin)
            ->post(route('admin.assignments.clone'), [
                'source_school_year_id' => $sy1->id,
                'target_school_year_id' => $sy2->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->adviser->id, (int) $sec2->fresh()->adviser_id);
        $this->assertSame(2, TeacherAssignment::query()->where('school_year_id', $sy2->id)->where('section_id', $sec2->id)->where('is_active', true)->count());

        // Non-admin blocked from bulk approve and assignment clone
        $this->actingAs($this->subjectTeacher->user)
            ->post(route('admin.submissions.approve-all', $t1))
            ->assertForbidden();

        $this->actingAs($this->subjectTeacher->user)
            ->post(route('admin.assignments.clone'), [
                'source_school_year_id' => $sy1->id,
                'target_school_year_id' => $sy2->id,
            ])
            ->assertForbidden();
    }
}
