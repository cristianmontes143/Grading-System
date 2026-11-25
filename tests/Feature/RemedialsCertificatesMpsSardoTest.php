<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingComponent;
use App\Models\GradingConfiguration;
use App\Models\RemedialClass;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAssessmentScore;
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

class RemedialsCertificatesMpsSardoTest extends TestCase
{
    use RefreshDatabase;

    public function test_remedials_registrar_certificates_and_mps_sardo_end_to_end(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $tUser = User::query()->create([
            'name' => 'Faculty Tester',
            'email' => 'faculty.capstone@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $tUser->roles()->attach(Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail()->id);
        $teacher = Teacher::query()->create([
            'user_id' => $tUser->id,
            'employee_number' => 'EMP-CAP1',
            'first_name' => 'Faculty',
            'last_name' => 'Tester',
            'is_active' => true,
        ]);

        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy);
        $t1 = $sy->gradingPeriods()->orderBy('sequence')->firstOrFail();

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
            'name' => 'Emerald',
            'capacity' => 40,
        ]);

        $subject = Subject::query()
            ->where('curriculum_id', $matatag->id)
            ->where('grade_level_id', $g7->id)
            ->where('is_graded', true)
            ->whereNull('parent_subject_id')
            ->firstOrFail();

        $assignment = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'student_number' => '2026-7901',
            'lrn' => '100000007901',
            'first_name' => 'Carlo',
            'last_name' => 'Mendoza',
            'gender' => 'Male',
            'date_of_birth' => '2013-07-12',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $enrollment = app(EnrollmentService::class)->enrollStudent($student, $section);

        // 1. Remedial Classes & SF10 Integration
        $this->actingAs($admin)
            ->post(route('admin.remedials.store'), [
                'student_enrollment_id' => $enrollment->id,
                'subject_id' => $subject->id,
                'final_rating' => 72,
                'remedial_class_mark' => 84,
                'conducted_from' => '2027-04-05',
                'conducted_to' => '2027-05-10',
                'conducted_by' => 'Ms. Elena Cruz',
            ])
            ->assertRedirect();

        $remedial = RemedialClass::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        $this->assertEquals(78.0, $remedial->recomputed_final_grade);
        $this->assertSame(RemedialClass::REMARKS_PASSED, $remedial->remarks);

        $this->actingAs($admin)
            ->get(route('admin.remedials.index', ['school_year_id' => $sy->id]))
            ->assertOk()
            ->assertSee('Mendoza, Carlo')
            ->assertSee('PASSED');

        // Remedial class appears on SF10 Permanent Record + Remedial Certificate PDF generates
        $this->actingAs($admin)
            ->get(route('admin.students.sf10', $student))
            ->assertOk()
            ->assertSee('Remedial Classes / Recomputed Final Grades')
            ->assertSee($subject->name);

        $this->actingAs($admin)
            ->get(route('admin.remedials.certificate', $enrollment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // 2. Registrar Official Certificates Hub (Enrollment, Good Moral, GWA/Ranking)
        $this->actingAs($admin)
            ->get(route('admin.certificates.index', ['school_year_id' => $sy->id]))
            ->assertOk()
            ->assertSee('Mendoza, Carlo');

        foreach (['enrollment', 'good_moral', 'gwa_ranking'] as $certType) {
            $this->actingAs($admin)
                ->get(route('admin.certificates.generate', [
                    'enrollment' => $enrollment,
                    'type' => $certType,
                    'purpose' => 'Scholarship Verification',
                ]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }

        // 3. DepEd MPS & SARDO Attendance Early-Warning Monitor
        $component = GradingComponent::query()->firstOrFail();

        $assessment = Assessment::query()->create([
            'teacher_assignment_id' => $assignment->id,
            'grading_period_id' => $t1->id,
            'grading_component_id' => $component->id,
            'title' => 'T1 Long Quiz 1',
            'assessment_type' => 'quiz',
            'max_score' => 50,
            'assessment_date' => '2026-07-20',
            'is_published' => true,
            'created_by' => $tUser->id,
        ]);

        StudentAssessmentScore::query()->create([
            'assessment_id' => $assessment->id,
            'student_enrollment_id' => $enrollment->id,
            'raw_score' => 40, // 80.00% MPS -> MET (>=75%)
            'status' => StudentAssessmentScore::STATUS_SCORED,
            'encoded_by' => $tUser->id,
        ]);

        // 5 absent out of 20 school days = 25.0% -> CRITICAL SARDO (>=20%)
        AttendanceRecord::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'grading_period_id' => $t1->id,
            'month_number' => 7,
            'year_number' => 2026,
            'school_days' => 20,
            'days_present' => 15,
            'days_absent' => 5,
            'days_tardy' => 2,
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.mps-sardo.index', ['school_year_id' => $sy->id, 'grading_period_id' => $t1->id]))
            ->assertOk()
            ->assertSee('80.00%')
            ->assertSee('CRITICAL SARDO')
            ->assertSee('Mendoza, Carlo');

        $this->actingAs($admin)
            ->get(route('admin.mps-sardo.print', ['school_year_id' => $sy->id]))
            ->assertOk()
            ->assertSee('DEPED MEAN PERCENTAGE SCORE (MPS)');

        $this->actingAs($admin)
            ->get(route('admin.mps-sardo.export', ['school_year_id' => $sy->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Delete remedial record works cleanly
        $this->actingAs($admin)
            ->delete(route('admin.remedials.destroy', $remedial))
            ->assertRedirect();
        $this->assertDatabaseMissing('remedial_classes', ['id' => $remedial->id]);

        // 4. RBAC Enforcement: non-admin subject teacher is blocked
        $this->actingAs($tUser)->get(route('admin.remedials.index'))->assertForbidden();
        $this->actingAs($tUser)->get(route('admin.certificates.index'))->assertForbidden();
        $this->actingAs($tUser)->get(route('admin.mps-sardo.index'))->assertForbidden();
    }
}
