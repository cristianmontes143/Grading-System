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
use App\Models\StudentEnrollment;
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

class PermanentRecordAndPromotionTest extends TestCase
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
        $this->adviser = $this->makeTeacher('EMP-P1', 'Rosa', 'Mendoza', 'rosa.adv@school.local', Role::ADVISER);
        $this->subjectTeacher = $this->makeTeacher('EMP-P2', 'Carlo', 'Diaz', 'carlo.sub@school.local', Role::SUBJECT_TEACHER);
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

    public function test_batch_promotion_rolls_learners_into_next_school_year_and_multi_year_sf10_preserves_historical_curricula(): void
    {
        $legacy = Curriculum::query()->where('code', 'K12-LEGACY-2015')->firstOrFail();
        $sshs = Curriculum::query()->where('code', 'SSHS-2026')->firstOrFail();
        $g10 = GradeLevel::query()->where('code', 'G10')->firstOrFail();
        $g11 = GradeLevel::query()->where('code', 'G11')->firstOrFail();

        // SY 2025-2026 (Legacy K-12 for G10)
        $sy1 = SchoolYear::query()->create([
            'name' => '2025-2026',
            'start_date' => '2025-06-16',
            'end_date' => '2026-03-31',
            'term_structure' => 'quarter',
            'is_active' => false,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy1);
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy1->id,
            'grade_level_id' => $g10->id,
            'curriculum_id' => $legacy->id,
        ]);

        // SY 2026-2027 (Strengthened SHS for G11)
        $sy2 = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy2);
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy2->id,
            'grade_level_id' => $g11->id,
            'curriculum_id' => $sshs->id,
        ]);

        $g10Section = Section::query()->create([
            'school_year_id' => $sy1->id,
            'grade_level_id' => $g10->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Sampaguita',
            'capacity' => 40,
        ]);

        $g11Section = Section::query()->create([
            'school_year_id' => $sy2->id,
            'grade_level_id' => $g11->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Narra',
            'capacity' => 40,
        ]);

        $student = Student::query()->create([
            'student_number' => '2025-0001',
            'lrn' => '100000002025',
            'first_name' => 'Lia',
            'last_name' => 'Villanueva',
            'gender' => 'Female',
            'date_of_birth' => '2009-09-12',
            'status' => Student::STATUS_ACTIVE,
        ]);

        $g10Enrollment = app(EnrollmentService::class)->enrollStudent($student, $g10Section);

        // Populate official grades for all G10 Legacy subjects across all 4 quarters
        $g10Subjects = Subject::query()
            ->where('curriculum_id', $legacy->id)
            ->where('grade_level_id', $g10->id)
            ->where('is_active', true)
            ->where('is_graded', true)
            ->whereNull('parent_subject_id')
            ->get();

        $this->assertNotEmpty($g10Subjects);

        $config = GradingConfiguration::query()->firstOrFail();
        $sy1Periods = $sy1->gradingPeriods()->get();

        foreach ($g10Subjects as $sub) {
            $ta = TeacherAssignment::query()->create([
                'school_year_id' => $sy1->id,
                'section_id' => $g10Section->id,
                'subject_id' => $sub->id,
                'teacher_id' => $this->adviser->id,
                'is_active' => true,
            ]);

            foreach ($sy1Periods as $p) {
                QuarterlyGrade::query()->create([
                    'student_enrollment_id' => $g10Enrollment->id,
                    'subject_id' => $sub->id,
                    'teacher_assignment_id' => $ta->id,
                    'grading_period_id' => $p->id,
                    'grading_configuration_id' => $config->id,
                    'initial_grade' => 88.0,
                    'transmuted_grade' => 90.0,
                    'final_grade' => 90.0,
                    'remarks' => 'Outstanding',
                    'is_official' => true,
                    'is_locked' => true,
                    'computed_at' => now(),
                ]);
            }
        }

        // Non-admin teacher is blocked from Batch Promotion
        $this->actingAs($this->subjectTeacher->user)
            ->get(route('admin.promotions.index'))
            ->assertForbidden();

        // Admin views Batch Promotion evaluation page and promotes Lia from G10 Sampaguita -> G11 Narra (SY 2026-2027)
        $this->actingAs($this->admin)
            ->get(route('admin.promotions.index', [
                'source_school_year_id' => $sy1->id,
                'source_section_id' => $g10Section->id,
            ]))
            ->assertOk()
            ->assertSee('Villanueva, Lia')
            ->assertSee('Promoted');

        $this->actingAs($this->admin)
            ->post(route('admin.promotions.execute'), [
                'source_section_id' => $g10Section->id,
                'action' => 'promote',
                'target_section_id' => $g11Section->id,
                'enrollment_ids' => [$g10Enrollment->id],
            ])
            ->assertRedirect();

        $this->assertSame(StudentEnrollment::STATUS_PROMOTED, $g10Enrollment->fresh()->enrollment_status);

        $g11Enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('school_year_id', $sy2->id)
            ->where('is_current', true)
            ->firstOrFail();

        $this->assertSame($g11Section->id, (int) $g11Enrollment->section_id);
        $this->assertSame($sshs->id, (int) $g11Enrollment->curriculum_id);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'student.batch_promote',
            'student_id' => $student->id,
        ]);

        // Multi-Year SF10 Permanent Record shows BOTH Legacy SY 2025-2026 AND Strengthened SHS SY 2026-2027
        $this->actingAs($this->admin)
            ->get(route('admin.students.sf10', $student))
            ->assertOk()
            ->assertSee('SY 2025-2026')
            ->assertSee('SY 2026-2027')
            ->assertSee($legacy->name)
            ->assertSee($sshs->name);

        // SF10 PDF streams cleanly
        $this->actingAs($this->admin)
            ->get(route('admin.students.sf10', ['student' => $student, 'pdf' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Adviser of Lia's section can view Lia's SF10; unrelated teacher gets 403
        $this->actingAs($this->adviser->user)
            ->get(route('adviser.students.sf10', $g11Enrollment))
            ->assertOk()
            ->assertSee('Villanueva, Lia');

        $this->actingAs($this->subjectTeacher->user)
            ->get(route('adviser.students.sf10', $g11Enrollment))
            ->assertForbidden();
    }

    public function test_batch_graduation_marks_grade_12_learner_as_graduated(): void
    {
        $shs = Curriculum::query()->where('code', 'SSHS-2026')->firstOrFail();
        $g12 = GradeLevel::query()->where('code', 'G12')->firstOrFail();

        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy);
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g12->id,
            'curriculum_id' => $shs->id,
        ]);

        $section = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g12->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'STEM-A',
            'capacity' => 40,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-1201',
            'lrn' => '100000001201',
            'first_name' => 'Marco',
            'last_name' => 'Alvarez',
            'gender' => 'Male',
            'date_of_birth' => '2008-03-10',
            'status' => Student::STATUS_ACTIVE,
        ]);

        $enrollment = app(EnrollmentService::class)->enrollStudent($student, $section);

        $this->actingAs($this->admin)
            ->post(route('admin.promotions.execute'), [
                'source_section_id' => $section->id,
                'action' => 'graduate',
                'enrollment_ids' => [$enrollment->id],
            ])
            ->assertRedirect();

        $this->assertSame(Student::STATUS_GRADUATED, $student->fresh()->status);
        $this->assertSame(StudentEnrollment::STATUS_COMPLETED, $enrollment->fresh()->enrollment_status);
    }
}
