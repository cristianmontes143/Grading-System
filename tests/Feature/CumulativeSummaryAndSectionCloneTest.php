<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingConfiguration;
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
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CumulativeSummaryAndSectionCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cumulative_subject_summary_export_load_slip_and_admin_section_cloning(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $sy1 = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy1);

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

        $tUser = User::query()->create([
            'name' => 'Teresa Lim',
            'email' => 'teresa.lim@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $tUser->roles()->attach(Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail()->id);
        $teacher = Teacher::query()->create([
            'user_id' => $tUser->id,
            'employee_number' => 'EMP-TL1',
            'first_name' => 'Teresa',
            'last_name' => 'Lim',
            'specialization' => 'Mathematics',
            'is_active' => true,
        ]);

        $sec1 = Section::query()->create([
            'school_year_id' => $sy1->id,
            'grade_level_id' => $g7->id,
            'name' => 'Jade',
            'room' => 'Room 101',
            'capacity' => 45,
        ]);
        Section::query()->create([
            'school_year_id' => $sy1->id,
            'grade_level_id' => $g7->id,
            'name' => 'Pearl',
            'room' => 'Room 102',
            'capacity' => 45,
        ]);

        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();
        $ta = TeacherAssignment::query()->create([
            'school_year_id' => $sy1->id,
            'section_id' => $sec1->id,
            'subject_id' => $math->id,
            'teacher_id' => $teacher->id,
            'schedule_summary' => 'MWF 8:00-9:00 AM',
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-6101',
            'lrn' => '100000006101',
            'first_name' => 'Nathan',
            'last_name' => 'Aquino',
            'gender' => 'Male',
            'date_of_birth' => '2013-02-20',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $enr = app(EnrollmentService::class)->enrollStudent($student, $sec1);
        $config = GradingConfiguration::query()->firstOrFail();

        // Populate T1=86, T2=90, T3=94 -> Final Subject Grade = 90
        $scores = [86, 90, 94];
        foreach ($sy1->gradingPeriods()->get() as $idx => $period) {
            QuarterlyGrade::query()->create([
                'student_enrollment_id' => $enr->id,
                'subject_id' => $math->id,
                'teacher_assignment_id' => $ta->id,
                'grading_period_id' => $period->id,
                'grading_configuration_id' => $config->id,
                'initial_grade' => $scores[$idx],
                'transmuted_grade' => $scores[$idx],
                'final_grade' => $scores[$idx],
                'remarks' => 'Passed',
                'is_official' => true,
                'is_locked' => true,
                'computed_at' => now(),
            ]);
        }

        // Teacher views Full-Year Cumulative Subject Summary & downloads Excel
        $this->actingAs($tUser)
            ->get(route('teacher.classes.summary', $ta))
            ->assertOk()
            ->assertSee('Aquino, Nathan')
            ->assertSee('90')
            ->assertSee('100%');

        $this->actingAs($tUser)
            ->get(route('teacher.classes.summary.export', $ta))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Teacher prints Official Teaching Load Slip
        $this->actingAs($tUser)
            ->get(route('teacher.classes.print-load'))
            ->assertOk()
            ->assertSee('OFFICIAL FACULTY TEACHING LOAD')
            ->assertSee('Teresa Lim')
            ->assertSee('MATH7');

        // Admin clones sections from SY 2026-2027 into SY 2027-2028 (copies 2 sections, skips duplicates on repeat)
        $this->actingAs($admin)
            ->post(route('admin.sections.clone'), [
                'source_school_year_id' => $sy1->id,
                'target_school_year_id' => $sy2->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, Section::query()->where('school_year_id', $sy2->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'section.cloned_from_school_year']);

        // Repeating clone does not duplicate sections
        $this->actingAs($admin)
            ->post(route('admin.sections.clone'), [
                'source_school_year_id' => $sy1->id,
                'target_school_year_id' => $sy2->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, Section::query()->where('school_year_id', $sy2->id)->count());

        // Non-admin cannot clone sections
        $this->actingAs($tUser)
            ->post(route('admin.sections.clone'), [
                'source_school_year_id' => $sy1->id,
                'target_school_year_id' => $sy2->id,
            ])
            ->assertForbidden();
    }
}
