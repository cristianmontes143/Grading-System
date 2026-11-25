<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingConfiguration;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
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

class ParentPortalAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();
    }

    public function test_parent_portal_verifies_three_factors_shows_only_official_grades_and_respects_admin_toggle(): void
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

        $tUser = User::query()->create([
            'name' => 'Adviser Test',
            'email' => 'adv-portal@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $tUser->roles()->attach(Role::query()->where('name', Role::ADVISER)->firstOrFail()->id);
        $teacher = Teacher::query()->create([
            'user_id' => $tUser->id,
            'employee_number' => 'EMP-PP1',
            'first_name' => 'Adviser',
            'last_name' => 'Test',
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $teacher->id,
            'name' => 'Diamond',
            'capacity' => 40,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-5050',
            'lrn' => '100000005050',
            'first_name' => 'Hannah',
            'last_name' => 'Soriano',
            'gender' => 'Female',
            'date_of_birth' => '2013-08-22',
            'status' => Student::STATUS_ACTIVE,
        ]);

        $enrollment = app(EnrollmentService::class)->enrollStudent($student, $section);
        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();
        $eng = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'ENG7')->firstOrFail();
        $config = GradingConfiguration::query()->firstOrFail();

        $mathTa = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $section->id,
            'subject_id' => $math->id,
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);
        $engTa = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $section->id,
            'subject_id' => $eng->id,
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        // Official Math grade = 94 (SHOULD be visible)
        QuarterlyGrade::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $math->id,
            'teacher_assignment_id' => $mathTa->id,
            'grading_period_id' => $t1->id,
            'grading_configuration_id' => $config->id,
            'initial_grade' => 93.0,
            'transmuted_grade' => 94.0,
            'final_grade' => 94.0,
            'remarks' => 'Outstanding',
            'is_official' => true,
            'is_locked' => true,
            'computed_at' => now(),
        ]);

        // Unofficial draft English grade = 68 (MUST NOT leak to parent portal!)
        QuarterlyGrade::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $eng->id,
            'teacher_assignment_id' => $engTa->id,
            'grading_period_id' => $t1->id,
            'grading_configuration_id' => $config->id,
            'initial_grade' => 60.0,
            'transmuted_grade' => 68.0,
            'final_grade' => 68.0,
            'remarks' => 'Did Not Meet Expectations',
            'is_official' => false,
            'is_locked' => false,
            'computed_at' => now(),
        ]);

        // Wrong DOB fails verification
        $this->post(route('portal.grades.lookup'), [
            'student_number' => '2026-5050',
            'lrn' => '100000005050',
            'date_of_birth' => '2014-01-01',
        ])
            ->assertOk()
            ->assertSee('No matching learner record found')
            ->assertDontSee('Soriano, Hannah');

        // Exact 3-factor match displays Official Math grade (94) and hides Draft English grade (68)
        $this->post(route('portal.grades.lookup'), [
            'student_number' => '2026-5050',
            'lrn' => '100000005050',
            'date_of_birth' => '2013-08-22',
        ])
            ->assertOk()
            ->assertSee('Soriano, Hannah')
            ->assertSee('>94<', false)
            ->assertDontSee('>68<', false);

        // Admin disables Parent Portal -> lookups are blocked
        SchoolSetting::current()->update(['parent_portal_enabled' => false]);
        $this->post(route('portal.grades.lookup'), [
            'student_number' => '2026-5050',
            'lrn' => '100000005050',
            'date_of_birth' => '2013-08-22',
        ])
            ->assertOk()
            ->assertSee('Currently Closed')
            ->assertDontSee('Soriano, Hannah');
    }

    public function test_admin_readiness_checklist_loads_and_blocks_non_admins(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.readiness.index'))
            ->assertOk()
            ->assertSee('School-Year Readiness')
            ->assertSee('Readiness Checks Passed');

        $teacherUser = User::query()->create([
            'name' => 'Teacher Guard',
            'email' => 'guard-readiness@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $teacherUser->roles()->attach(Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail()->id);

        $this->actingAs($teacherUser)
            ->get(route('admin.readiness.index'))
            ->assertForbidden();
    }
}
