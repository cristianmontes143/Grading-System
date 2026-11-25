<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
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
use App\Services\Grading\GradeSubmissionService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Module7GradeCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SchoolYear $sy;
    private GradingPeriod $t1;
    private Section $secA;
    private Teacher $adviser;
    private Teacher $mathTeacher;
    private Teacher $engTeacher;
    private Teacher $sciTeacher;
    private TeacherAssignment $mathAssign;
    private TeacherAssignment $engAssign;
    private TeacherAssignment $sciAssign;
    private Student $student;
    private int $enrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $this->sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($this->sy);
        $this->t1 = $this->sy->gradingPeriods()->where('code', 'T1')->firstOrFail();
        $this->t1->update(['status' => GradingPeriod::STATUS_OPEN]);

        $sshs = Curriculum::query()->where('code', 'SSHS-2026')->firstOrFail();
        $g12 = GradeLevel::query()->where('code', 'G12')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $g12->id,
            'curriculum_id' => $sshs->id,
        ]);

        $this->adviser = $this->makeTeacher('T-1', 'Adviser', 'One', 't1@school.local', Role::ADVISER);
        $this->mathTeacher = $this->makeTeacher('T-2', 'Math', 'Two', 't2@school.local', Role::SUBJECT_TEACHER);
        $this->engTeacher = $this->makeTeacher('T-3', 'English', 'Three', 't3@school.local', Role::SUBJECT_TEACHER);
        $this->sciTeacher = $this->makeTeacher('T-4', 'Science', 'Four', 't4@school.local', Role::SUBJECT_TEACHER);

        $this->secA = Section::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $g12->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Section A',
            'capacity' => 40,
        ]);

        $math = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'MATH12', 'name' => 'Mathematics', 'weight_group' => 'shs_core']);
        $eng = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'ENG12', 'name' => 'English', 'weight_group' => 'shs_core']);
        $sci = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'SCI12', 'name' => 'Science', 'weight_group' => 'shs_core']);

        $this->mathAssign = TeacherAssignment::query()->create(['school_year_id' => $this->sy->id, 'section_id' => $this->secA->id, 'subject_id' => $math->id, 'teacher_id' => $this->mathTeacher->id]);
        $this->engAssign = TeacherAssignment::query()->create(['school_year_id' => $this->sy->id, 'section_id' => $this->secA->id, 'subject_id' => $eng->id, 'teacher_id' => $this->engTeacher->id]);
        $this->sciAssign = TeacherAssignment::query()->create(['school_year_id' => $this->sy->id, 'section_id' => $this->secA->id, 'subject_id' => $sci->id, 'teacher_id' => $this->sciTeacher->id]);

        $this->student = Student::query()->create([
            'student_number' => '2026-0001',
            'lrn' => '123456789012',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'gender' => 'Female',
            'date_of_birth' => '2009-01-15',
            'status' => Student::STATUS_ACTIVE,
        ]);

        $this->enrollmentId = app(EnrollmentService::class)->enrollStudent($this->student, $this->secA)->id;
    }

    private function makeTeacher(string $empNo, string $first, string $last, string $email, string $roleName): Teacher
    {
        $user = User::query()->create([
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id);

        return Teacher::query()->create([
            'user_id' => $user->id,
            'employee_number' => $empNo,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
        ]);
    }

    private function encodeSampleScores(TeacherAssignment $assignment, float $scorePct = 90.0): void
    {
        $gb = app(GradebookService::class);
        $config = $gb->resolveConfiguration($assignment);
        $leaves = $gb->getLeafComponents($config);

        $scores = [];
        foreach ($leaves as $leaf) {
            $assessment = Assessment::query()->create([
                'teacher_assignment_id' => $assignment->id,
                'grading_period_id' => $this->t1->id,
                'grading_component_id' => $leaf['id'],
                'title' => "Task {$leaf['code']}",
                'assessment_type' => 'quiz',
                'max_score' => 100,
            ]);
            $scores[$this->enrollmentId][$assessment->id] = $scorePct;
        }

        $gb->saveBulkScores($assignment, $this->t1, $scores, $assignment->teacher->user);
    }

    public function test_automatic_grade_collection_only_exposes_submitted_grades_and_notifies_adviser(): void
    {
        // Math & English submit; Science encodes draft scores ONLY (does NOT submit)
        $this->encodeSampleScores($this->mathAssign, 92.0);
        $this->encodeSampleScores($this->engAssign, 88.0);
        $this->encodeSampleScores($this->sciAssign, 80.0);

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.submit', [$this->mathAssign, $this->t1]))
            ->assertRedirect();

        $this->actingAs($this->engTeacher->user)
            ->post(route('teacher.gradebook.submit', [$this->engAssign, $this->t1]))
            ->assertRedirect();

        // Adviser received 2 automatic notifications
        $this->assertSame(2, $this->adviser->user->unreadNotifications()->count());

        // Inspect Automatic Grade Collection Matrix
        $matrix = app(GradeSubmissionService::class)->getSectionCollectionMatrix($this->secA, $this->t1);

        $this->assertSame(3, $matrix['total_subjects']);
        $this->assertSame(2, $matrix['submitted_subjects']);
        $this->assertSame(1, $matrix['pending_subjects']);
        $this->assertFalse($matrix['ready_for_report_cards']);

        // Official grades contain Math & English ONLY; Science draft is NOT exposed as official!
        $this->assertArrayHasKey($this->mathAssign->subject_id, $matrix['official_grades_matrix'][$this->enrollmentId]);
        $this->assertArrayHasKey($this->engAssign->subject_id, $matrix['official_grades_matrix'][$this->enrollmentId]);
        $this->assertArrayNotHasKey($this->sciAssign->subject_id, $matrix['official_grades_matrix'][$this->enrollmentId]);

        // Adviser dashboard renders cleanly showing Submitted & Draft/Pending
        $this->actingAs($this->adviser->user)
            ->get(route('adviser.sections.show', ['section' => $this->secA, 'period_id' => $this->t1->id]))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Submitted')
            ->assertSee('Science');

        // Math teacher can no longer edit after submitting (locked!)
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssign, $this->t1]), ['scores' => []])
            ->assertSessionHasErrors('grading_period_id');
    }

    public function test_admin_can_return_submission_approve_and_perform_audited_grade_override(): void
    {
        $this->encodeSampleScores($this->mathAssign, 85.0);

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.submit', [$this->mathAssign, $this->t1]))
            ->assertRedirect();

        $submission = GradeSubmission::query()->where('teacher_assignment_id', $this->mathAssign->id)->firstOrFail();

        // Adviser CANNOT return or override -> 403
        $this->actingAs($this->adviser->user)
            ->post(route('admin.submissions.return', $submission), ['reason' => 'Unauthorized'])
            ->assertForbidden();

        // Admin returns submission with reason -> unlocks Math grades & notifies Math teacher
        $this->actingAs($this->admin)
            ->post(route('admin.submissions.return', $submission), [
                'reason' => 'Verify missing quiz record before finalization',
            ])
            ->assertRedirect();

        $this->assertSame(GradeSubmission::STATUS_RETURNED, $submission->fresh()->status);
        $this->assertSame(1, $this->mathTeacher->user->unreadNotifications()->count());

        $qg = QuarterlyGrade::query()
            ->where('teacher_assignment_id', $this->mathAssign->id)
            ->where('student_enrollment_id', $this->enrollmentId)
            ->firstOrFail();
        $this->assertFalse($qg->is_official);
        $this->assertFalse($qg->is_locked);

        // Math teacher re-submits, Admin approves, then Admin performs an authorized override with mandatory reason
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.submit', [$this->mathAssign, $this->t1]))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.submissions.approve', $submission))
            ->assertRedirect();
        $this->assertSame(GradeSubmission::STATUS_APPROVED, $submission->fresh()->status);

        $previousGrade = $qg->fresh()->display_grade;

        $this->actingAs($this->admin)
            ->post(route('admin.grades.override', $qg), [
                'final_grade' => 94,
                'reason' => 'Principal-approved clerical correction Memo #2026-07',
            ])
            ->assertRedirect();

        $this->assertSame(94.0, $qg->fresh()->final_grade);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'grade.admin_override',
            'teacher_id' => $this->mathTeacher->id,
            'student_id' => $this->student->id,
            'subject_id' => $this->mathAssign->subject_id,
            'previous_grade' => $previousGrade,
            'updated_grade' => '94',
            'reason' => 'Principal-approved clerical correction Memo #2026-07',
        ]);
    }
}
