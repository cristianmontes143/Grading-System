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
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CertificatesSf5AndGradebookStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_honor_certificates_pdf_sf5_export_and_gradebook_analytics_strip(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

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

        $adviserUser = User::query()->create([
            'name' => 'Adviser Cert',
            'email' => 'adviser-cert@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $adviserUser->roles()->attach(Role::query()->where('name', Role::ADVISER)->firstOrFail()->id);
        $adviser = Teacher::query()->create([
            'user_id' => $adviserUser->id,
            'employee_number' => 'EMP-CERT1',
            'first_name' => 'Adviser',
            'last_name' => 'Cert',
            'is_active' => true,
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other Teacher',
            'email' => 'other-cert@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $otherUser->roles()->attach(Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail()->id);
        Teacher::query()->create([
            'user_id' => $otherUser->id,
            'employee_number' => 'EMP-CERT2',
            'first_name' => 'Other',
            'last_name' => 'Teacher',
            'is_active' => true,
        ]);

        $section = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $adviser->id,
            'name' => 'Emerald',
            'capacity' => 40,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-8801',
            'lrn' => '100000008801',
            'first_name' => 'Andrea',
            'last_name' => 'Velasco',
            'gender' => 'Female',
            'date_of_birth' => '2013-11-11',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $enrollment = app(EnrollmentService::class)->enrollStudent($student, $section);

        $subjects = Subject::query()
            ->where('curriculum_id', $matatag->id)
            ->where('grade_level_id', $g7->id)
            ->where('is_active', true)
            ->where('is_graded', true)
            ->whereNull('parent_subject_id')
            ->get();

        $config = GradingConfiguration::query()->firstOrFail();
        $firstTa = null;

        foreach ($subjects as $sub) {
            $ta = TeacherAssignment::query()->create([
                'school_year_id' => $sy->id,
                'section_id' => $section->id,
                'subject_id' => $sub->id,
                'teacher_id' => $adviser->id,
                'is_active' => true,
            ]);
            $firstTa ??= $ta;

            QuarterlyGrade::query()->create([
                'student_enrollment_id' => $enrollment->id,
                'subject_id' => $sub->id,
                'teacher_assignment_id' => $ta->id,
                'grading_period_id' => $t1->id,
                'grading_configuration_id' => $config->id,
                'initial_grade' => 95.0,
                'transmuted_grade' => 96.0,
                'final_grade' => 96.0,
                'remarks' => 'Outstanding',
                'is_official' => true,
                'is_locked' => true,
                'computed_at' => now(),
            ]);
        }

        // Batch section Honor Certificates PDF streams cleanly
        $this->actingAs($adviserUser)
            ->get(route('adviser.sections.certificates', ['section' => $section, 'period_id' => $t1->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Individual Honor Certificate PDF streams cleanly
        $this->actingAs($adviserUser)
            ->get(route('adviser.students.certificate', ['enrollment' => $enrollment, 'period_id' => $t1->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // DepEd SF5 Promotion Summary Excel (.xlsx) downloads cleanly
        $this->actingAs($adviserUser)
            ->get(route('adviser.sections.export-sf5', $section))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Unrelated subject teacher cannot access section certificates or SF5 export
        $this->actingAs($otherUser)
            ->get(route('adviser.sections.certificates', ['section' => $section, 'period_id' => $t1->id]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->get(route('adviser.sections.export-sf5', $section))
            ->assertForbidden();

        // Gradebook renders the live Class Grade Analytics Strip (Highest, Lowest, Passing Rate)
        $this->actingAs($adviserUser)
            ->get(route('teacher.gradebook.show', [$firstTa, $t1]))
            ->assertOk()
            ->assertSee('Highest Grade')
            ->assertSee('Passing Rate')
            ->assertSee('100%');
    }
}
