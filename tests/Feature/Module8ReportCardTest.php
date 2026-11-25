<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Grading\GradebookService;
use App\Services\Grading\GradeSubmissionService;
use App\Services\Reports\ReportCardAssemblyService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Module8ReportCardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SchoolYear $sy;
    private GradingPeriod $t1;
    private Section $secA;
    private Teacher $adviser;
    private Teacher $otherAdviser;
    private Teacher $mathTeacher;
    private Teacher $engTeacher;
    private TeacherAssignment $mathAssign;
    private TeacherAssignment $engAssign;
    private StudentEnrollment $enrollment;

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

        $this->adviser = $this->makeTeacher('T-A1', 'Adviser', 'One', 'a1@school.local', Role::ADVISER);
        $this->otherAdviser = $this->makeTeacher('T-A2', 'Adviser', 'Two', 'a2@school.local', Role::ADVISER);
        $this->mathTeacher = $this->makeTeacher('T-M1', 'Math', 'Teacher', 'm1@school.local', Role::SUBJECT_TEACHER);
        $this->engTeacher = $this->makeTeacher('T-E1', 'English', 'Teacher', 'e1@school.local', Role::SUBJECT_TEACHER);

        $this->secA = Section::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $g12->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Section A',
            'capacity' => 40,
        ]);

        $math = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'MATH12', 'name' => 'Mathematics', 'weight_group' => 'shs_core']);
        $eng = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'ENG12', 'name' => 'English', 'weight_group' => 'shs_core']);

        $this->mathAssign = TeacherAssignment::query()->create(['school_year_id' => $this->sy->id, 'section_id' => $this->secA->id, 'subject_id' => $math->id, 'teacher_id' => $this->mathTeacher->id]);
        $this->engAssign = TeacherAssignment::query()->create(['school_year_id' => $this->sy->id, 'section_id' => $this->secA->id, 'subject_id' => $eng->id, 'teacher_id' => $this->engTeacher->id]);

        $student = Student::query()->create([
            'student_number' => '2026-0001',
            'lrn' => '123456789012',
            'first_name' => 'Clara',
            'last_name' => 'Reyes',
            'gender' => 'Female',
            'date_of_birth' => '2009-03-20',
            'status' => Student::STATUS_ACTIVE,
        ]);

        $this->enrollment = app(EnrollmentService::class)->enrollStudent($student, $this->secA);
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

    private function gradeAndSubmit(TeacherAssignment $assignment, float $pct): void
    {
        $gb = app(GradebookService::class);
        $config = $gb->resolveConfiguration($assignment);
        $scores = [];
        foreach ($gb->getLeafComponents($config) as $leaf) {
            $a = Assessment::query()->create([
                'teacher_assignment_id' => $assignment->id,
                'grading_period_id' => $this->t1->id,
                'grading_component_id' => $leaf['id'],
                'title' => "Task {$leaf['code']}",
                'assessment_type' => 'quiz',
                'max_score' => 100,
            ]);
            $scores[$this->enrollment->id][$a->id] = $pct;
        }
        $gb->saveBulkScores($assignment, $this->t1, $scores, $assignment->teacher->user);
        app(GradeSubmissionService::class)->submit($assignment, $this->t1, $assignment->teacher->user);
    }

    public function test_official_report_card_is_blocked_when_grades_are_missing_and_succeeds_once_all_submitted(): void
    {
        // Only Math submitted; English is still pending
        $this->gradeAndSubmit($this->mathAssign, 90.0);

        // Adviser CANNOT generate official PDF while English is missing
        $this->actingAs($this->adviser->user)
            ->get(route('adviser.report-cards.individual', ['enrollment' => $this->enrollment, 'period_id' => $this->t1->id]))
            ->assertSessionHasErrors('report_card');

        // Adviser CAN generate explicitly watermarked Draft PDF
        $draftResp = $this->actingAs($this->adviser->user)
            ->get(route('adviser.report-cards.individual', ['enrollment' => $this->enrollment, 'period_id' => $this->t1->id, 'draft' => 1]));
        $draftResp->assertOk();
        $this->assertStringStartsWith('%PDF-', $draftResp->getContent());
        $this->assertDatabaseHas('report_cards', [
            'student_enrollment_id' => $this->enrollment->id,
            'is_official' => false,
            'has_missing_grades' => true,
        ]);

        // Now English teacher submits -> 100% complete!
        $this->gradeAndSubmit($this->engAssign, 88.0);

        // Save attendance & observed values as Adviser
        $this->actingAs($this->adviser->user)
            ->post(route('adviser.records.attendance.save', $this->secA), [
                'month_number' => 7,
                'year_number' => 2026,
                'attendance' => [
                    $this->enrollment->id => ['school_days' => 22, 'days_present' => 21, 'days_absent' => 1, 'days_tardy' => 0],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($this->adviser->user)
            ->post(route('adviser.records.values.save', $this->secA), [
                'grading_period_id' => $this->t1->id,
                'values' => [
                    $this->enrollment->id => ['MD1' => 'AO', 'MT1' => 'AO'],
                ],
            ])
            ->assertRedirect();

        // Official Individual PDF succeeds
        $officialResp = $this->actingAs($this->adviser->user)
            ->get(route('adviser.report-cards.individual', ['enrollment' => $this->enrollment, 'period_id' => $this->t1->id]));
        $officialResp->assertOk();
        $this->assertStringStartsWith('%PDF-', $officialResp->getContent());

        // Official Batch Section PDF succeeds
        $batchResp = $this->actingAs($this->adviser->user)
            ->get(route('adviser.report-cards.batch', ['section' => $this->secA, 'period_id' => $this->t1->id]));
        $batchResp->assertOk();
        $this->assertStringStartsWith('%PDF-', $batchResp->getContent());

        $this->assertTrue(ReportCard::query()->where('student_enrollment_id', $this->enrollment->id)->where('is_official', true)->exists());

        // Another adviser CANNOT generate report cards for Section A -> 403
        $this->actingAs($this->otherAdviser->user)
            ->get(route('adviser.report-cards.individual', ['enrollment' => $this->enrollment, 'period_id' => $this->t1->id]))
            ->assertForbidden();
    }

    public function test_historical_report_card_renders_under_original_legacy_curriculum_even_after_matatag_transition(): void
    {
        $syOld = SchoolYear::query()->create([
            'name' => '2024-2025',
            'start_date' => '2024-07-29',
            'end_date' => '2025-04-15',
            'term_structure' => 'quarterly',
            'is_active' => false,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($syOld);

        $legacy = Curriculum::query()->where('code', 'K12-LEGACY-2015')->firstOrFail();
        $g10 = GradeLevel::query()->where('code', 'G10')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $syOld->id,
            'grade_level_id' => $g10->id,
            'curriculum_id' => $legacy->id,
        ]);

        $oldSection = Section::query()->create([
            'school_year_id' => $syOld->id,
            'grade_level_id' => $g10->id,
            'name' => 'G10 Rizal',
            'capacity' => 45,
        ]);

        $historicalEnrollment = app(EnrollmentService::class)->enrollStudent($this->enrollment->student, $oldSection);
        $assembled = app(ReportCardAssemblyService::class)->assemble($historicalEnrollment);

        $subjectCodes = collect($assembled['subject_rows'])->pluck('subject.code')->all();
        $this->assertSame('K12-LEGACY-2015', $assembled['enrollment']->curriculum->code);
        $this->assertContains('ESP10-K12', $subjectCodes);
        $this->assertNotContains('VE10', $subjectCodes);
    }

    public function test_admin_can_create_custom_report_card_template(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.report-card-templates.store'), [
                'name' => 'Custom School SF9 A4',
                'header_subtitle' => 'Progress Report Card',
                'disclaimer_label' => 'DepEd-Compatible Custom Template',
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'show_lrn' => '1',
                'show_attendance' => '1',
                'show_observed_values' => '1',
                'is_default' => '1',
            ])
            ->assertRedirect(route('admin.report-card-templates.index'));

        $this->assertDatabaseHas('report_card_templates', [
            'name' => 'Custom School SF9 A4',
            'is_default' => true,
        ]);
    }
}
