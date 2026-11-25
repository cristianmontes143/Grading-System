<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
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

class ClassRosterAndGradebookFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_and_subject_teacher_can_print_male_female_classroom_roster_and_use_gradebook_filters(): void
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

        $adviser = $this->makeTeacher('EMP-R1', 'Clara', 'Bautista', 'clara.r@school.local', Role::ADVISER);
        $subjectTeacher = $this->makeTeacher('EMP-R2', 'Diego', 'Ramos', 'diego.r@school.local', Role::SUBJECT_TEACHER);
        $outsider = $this->makeTeacher('EMP-R3', 'Other', 'Person', 'other.r@school.local', Role::SUBJECT_TEACHER);

        $section = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $adviser->id,
            'name' => 'Ruby',
            'capacity' => 40,
        ]);

        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();
        $assignment = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $section->id,
            'subject_id' => $math->id,
            'teacher_id' => $subjectTeacher->id,
            'is_active' => true,
        ]);

        $maleStudent = Student::query()->create([
            'student_number' => '2026-4101',
            'lrn' => '100000004101',
            'first_name' => 'Miguel',
            'last_name' => 'Santos',
            'gender' => 'Male',
            'date_of_birth' => '2013-03-01',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $femaleStudent = Student::query()->create([
            'student_number' => '2026-4102',
            'lrn' => '100000004102',
            'first_name' => 'Elena',
            'last_name' => 'Cruz',
            'gender' => 'Female',
            'date_of_birth' => '2013-05-05',
            'status' => Student::STATUS_ACTIVE,
        ]);

        app(EnrollmentService::class)->enrollStudent($maleStudent, $section);
        app(EnrollmentService::class)->enrollStudent($femaleStudent, $section);

        // Adviser prints advisory section roster (Male/Female split)
        $this->actingAs($adviser->user)
            ->get(route('adviser.sections.print-roster', $section))
            ->assertOk()
            ->assertSee('Male Learners (1)')
            ->assertSee('Female Learners (1)')
            ->assertSee('Santos, Miguel')
            ->assertSee('Cruz, Elena');

        // Subject teacher prints subject class roster
        $this->actingAs($subjectTeacher->user)
            ->get(route('teacher.classes.print-roster', $assignment))
            ->assertOk()
            ->assertSee('MATH7')
            ->assertSee('Diego Ramos')
            ->assertSee('Santos, Miguel');

        // Unassigned outsider teacher is forbidden from both rosters
        $this->actingAs($outsider->user)
            ->get(route('teacher.classes.print-roster', $assignment))
            ->assertForbidden();

        $this->actingAs($outsider->user)
            ->get(route('adviser.sections.print-roster', $section))
            ->assertForbidden();

        // Gradebook renders live learner search and missing-scores-only toggle
        $this->actingAs($subjectTeacher->user)
            ->get(route('teacher.gradebook.show', [$assignment, $t1]))
            ->assertOk()
            ->assertSee('Missing scores only')
            ->assertSee('Filter learner name or ID');
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
}
