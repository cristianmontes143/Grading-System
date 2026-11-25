<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingConfiguration;
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
use App\Services\Reports\SectionSummaryService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicAnalyticsAndHonorRollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $adviser;
    private Teacher $otherTeacher;
    private SchoolYear $sy;
    private GradingPeriod $t1;
    private Section $section;
    private Curriculum $curriculum;
    private GradeLevel $g7;

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

        $this->curriculum = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $this->g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $this->g7->id,
            'curriculum_id' => $this->curriculum->id,
        ]);

        $this->adviser = $this->makeTeacher('EMP-ADV', 'Elena', 'Reyes', 'elena.adviser@school.local', Role::ADVISER);
        $this->otherTeacher = $this->makeTeacher('EMP-SUB', 'Mario', 'Cruz', 'mario.sub@school.local', Role::SUBJECT_TEACHER);

        $this->section = Section::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $this->g7->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Rizal',
            'capacity' => 40,
        ]);
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

    private function makeStudentAndEnroll(string $num, string $lrn, string $first, string $last): int
    {
        $student = Student::query()->create([
            'student_number' => $num,
            'lrn' => $lrn,
            'first_name' => $first,
            'last_name' => $last,
            'gender' => 'Female',
            'date_of_birth' => '2013-07-01',
            'status' => Student::STATUS_ACTIVE,
        ]);

        return app(EnrollmentService::class)->enrollStudent($student, $this->section)->id;
    }

    public function test_deped_honor_roll_ranking_master_sheet_export_and_admin_analytics(): void
    {
        $subjects = Subject::query()
            ->where('curriculum_id', $this->curriculum->id)
            ->where('grade_level_id', $this->g7->id)
            ->where('is_active', true)
            ->where('is_graded', true)
            ->whereNull('parent_subject_id')
            ->get();

        $config = GradingConfiguration::query()->firstOrFail();

        $assignmentsBySubject = [];
        foreach ($subjects as $subject) {
            $assignmentsBySubject[$subject->id] = TeacherAssignment::query()->create([
                'school_year_id' => $this->sy->id,
                'section_id' => $this->section->id,
                'subject_id' => $subject->id,
                'teacher_id' => $this->adviser->id,
                'is_active' => true,
            ]);
        }

        $highestId = $this->makeStudentAndEnroll('2026-1001', '100000001001', 'Clara', 'Abad');
        $honorsId = $this->makeStudentAndEnroll('2026-1002', '100000001002', 'Luis', 'Bautista');
        $atRiskId = $this->makeStudentAndEnroll('2026-1003', '100000001003', 'Pedro', 'Castro');

        foreach ($subjects as $idx => $subject) {
            $taId = $assignmentsBySubject[$subject->id]->id;

            // Clara: 98 across all subjects -> With Highest Honors (Rank 1)
            QuarterlyGrade::query()->create([
                'student_enrollment_id' => $highestId,
                'subject_id' => $subject->id,
                'teacher_assignment_id' => $taId,
                'grading_period_id' => $this->t1->id,
                'grading_configuration_id' => $config->id,
                'initial_grade' => 97.5,
                'transmuted_grade' => 98,
                'final_grade' => 98,
                'remarks' => 'Outstanding',
                'is_official' => true,
                'is_locked' => true,
                'computed_at' => now(),
            ]);

            // Luis: 92 across all subjects -> With Honors (Rank 2)
            QuarterlyGrade::query()->create([
                'student_enrollment_id' => $honorsId,
                'subject_id' => $subject->id,
                'teacher_assignment_id' => $taId,
                'grading_period_id' => $this->t1->id,
                'grading_configuration_id' => $config->id,
                'initial_grade' => 91.0,
                'transmuted_grade' => 92,
                'final_grade' => 92,
                'remarks' => 'Outstanding',
                'is_official' => true,
                'is_locked' => true,
                'computed_at' => now(),
            ]);

            // Pedro: first subject 72 (< 75), others 92 -> Disqualified from honors & flagged Needs Intervention!
            $pedroGrade = $idx === 0 ? 72 : 92;
            QuarterlyGrade::query()->create([
                'student_enrollment_id' => $atRiskId,
                'subject_id' => $subject->id,
                'teacher_assignment_id' => $taId,
                'grading_period_id' => $this->t1->id,
                'grading_configuration_id' => $config->id,
                'initial_grade' => $pedroGrade,
                'transmuted_grade' => $pedroGrade,
                'final_grade' => $pedroGrade,
                'remarks' => $pedroGrade < 75 ? 'Did Not Meet Expectations' : 'Outstanding',
                'is_official' => true,
                'is_locked' => true,
                'computed_at' => now(),
            ]);
        }

        $summary = app(SectionSummaryService::class)->buildSectionSummary($this->section, $this->t1);

        $this->assertSame(1, $summary['stats']['honor_counts'][SectionSummaryService::HONOR_HIGHEST]);
        $this->assertSame(1, $summary['stats']['honor_counts'][SectionSummaryService::HONOR_REGULAR]);
        $this->assertSame(2, $summary['stats']['total_honors']);
        $this->assertSame(1, $summary['stats']['at_risk_count']);

        $claraRow = $summary['learner_rows']->firstWhere('enrollment.id', $highestId);
        $luisRow = $summary['learner_rows']->firstWhere('enrollment.id', $honorsId);
        $pedroRow = $summary['learner_rows']->firstWhere('enrollment.id', $atRiskId);

        $this->assertSame(1, $claraRow['rank']);
        $this->assertSame(SectionSummaryService::HONOR_HIGHEST, $claraRow['honor']);
        $this->assertSame(2, $luisRow['rank']);
        $this->assertSame(SectionSummaryService::HONOR_REGULAR, $luisRow['honor']);
        $this->assertNull($pedroRow['honor']);
        $this->assertNotEmpty($pedroRow['failing_subjects']);

        // Adviser can view section honor roll, print master sheet, and download Excel master sheet
        $this->actingAs($this->adviser->user)
            ->get(route('adviser.sections.index'))
            ->assertOk()
            ->assertSee('Rizal');

        $this->actingAs($this->adviser->user)
            ->get(route('adviser.sections.show', [$this->section, 'period_id' => $this->t1->id]))
            ->assertOk()
            ->assertSee('With Highest Honors')
            ->assertSee('Abad, Clara');

        $this->actingAs($this->adviser->user)
            ->get(route('adviser.sections.print-summary', [$this->section, 'period_id' => $this->t1->id]))
            ->assertOk()
            ->assertSee('Official Academic Honor Roll');

        $this->actingAs($this->adviser->user)
            ->get(route('adviser.sections.export-summary', [$this->section, 'period_id' => $this->t1->id]))
            ->assertOk();

        // Non-adviser subject teacher cannot access another section's master sheet or Admin analytics
        $this->actingAs($this->otherTeacher->user)
            ->get(route('adviser.sections.export-summary', [$this->section, 'period_id' => $this->t1->id]))
            ->assertForbidden();

        $this->actingAs($this->otherTeacher->user)
            ->get(route('admin.analytics.index'))
            ->assertForbidden();

        // Admin can view school-wide Academic Analytics & Honor Roll
        $this->actingAs($this->admin)
            ->get(route('admin.analytics.index', ['school_year_id' => $this->sy->id, 'grading_period_id' => $this->t1->id]))
            ->assertOk()
            ->assertSee('School-Wide Academic Analytics')
            ->assertSee('Abad, Clara')
            ->assertSee('With Highest Honors')
            ->assertSee('Castro, Pedro');
    }
}
