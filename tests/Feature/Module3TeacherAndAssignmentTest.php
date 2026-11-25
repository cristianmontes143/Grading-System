<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module3TeacherAndAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();
    }

    private function createTeacherViaAdmin(string $empNo, string $first, string $last, string $email, string $role = Role::SUBJECT_TEACHER): Teacher
    {
        $this->actingAs($this->admin)
            ->post(route('admin.teachers.store'), [
                'employee_number' => $empNo,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password' => 'Teacher@1234',
                'role_name' => $role,
            ])
            ->assertRedirect(route('admin.teachers.index'));

        return Teacher::query()->with('user')->where('employee_number', $empNo)->firstOrFail();
    }

    public function test_flexible_cross_section_adviser_and_subject_teacher_assignments_and_strict_isolation(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);

        $sshs = Curriculum::query()->where('code', 'SSHS-2026')->firstOrFail();
        $g12 = GradeLevel::query()->where('code', 'G12')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g12->id,
            'curriculum_id' => $sshs->id,
            'implementation_status' => 'active',
        ]);

        // Create Grade 12 subjects matching the prompt example
        $math = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'MATH12', 'name' => 'Mathematics', 'weight_group' => 'shs_core']);
        $eng = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'ENG12', 'name' => 'English', 'weight_group' => 'shs_core']);
        $prog = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'PROG12', 'name' => 'Programming', 'weight_group' => 'shs_techpro']);
        $sci = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'SCI12', 'name' => 'Science', 'weight_group' => 'shs_core']);

        $secA = Section::query()->create(['school_year_id' => $sy->id, 'grade_level_id' => $g12->id, 'name' => 'Section A', 'capacity' => 40]);
        $secB = Section::query()->create(['school_year_id' => $sy->id, 'grade_level_id' => $g12->id, 'name' => 'Section B', 'capacity' => 40]);

        $t1 = $this->createTeacherViaAdmin('EMP-001', 'Ana', 'Cruz', 't1@school.local', Role::ADVISER);
        $t2 = $this->createTeacherViaAdmin('EMP-002', 'Ben', 'Reyes', 't2@school.local', Role::ADVISER);
        $t3 = $this->createTeacherViaAdmin('EMP-003', 'Carla', 'Lim', 't3@school.local', Role::SUBJECT_TEACHER);
        $t4 = $this->createTeacherViaAdmin('EMP-004', 'Dan', 'Santos', 't4@school.local', Role::SUBJECT_TEACHER);

        // Assign Advisers: Teacher 1 -> Sec A, Teacher 2 -> Sec B
        $this->actingAs($this->admin)->post(route('admin.assignments.adviser', $secA), ['adviser_id' => $t1->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.adviser', $secB), ['adviser_id' => $t2->id])->assertRedirect();

        // Section A subjects: Math=T2, Eng=T3, Prog=T1, Sci=T4
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secA), ['subject_id' => $math->id, 'teacher_id' => $t2->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secA), ['subject_id' => $eng->id, 'teacher_id' => $t3->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secA), ['subject_id' => $prog->id, 'teacher_id' => $t1->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secA), ['subject_id' => $sci->id, 'teacher_id' => $t4->id])->assertRedirect();

        // Section B subjects: Math=T1, Eng=T3, Prog=T4, Sci=T2
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secB), ['subject_id' => $math->id, 'teacher_id' => $t1->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secB), ['subject_id' => $eng->id, 'teacher_id' => $t3->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secB), ['subject_id' => $prog->id, 'teacher_id' => $t4->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secB), ['subject_id' => $sci->id, 'teacher_id' => $t2->id])->assertRedirect();

        // Verify Teacher 1 has 1 advisory section (Sec A) + 2 subject loads (Prog in Sec A, Math in Sec B)
        $this->assertSame(1, $t1->advisorySections()->count());
        $this->assertSame(2, $t1->activeAssignments()->count());

        $secAMathAssignment = TeacherAssignment::query()->where('section_id', $secA->id)->where('subject_id', $math->id)->firstOrFail();
        $secAProgAssignment = TeacherAssignment::query()->where('section_id', $secA->id)->where('subject_id', $prog->id)->firstOrFail();

        // Teacher 1 (Adviser of Sec A) CAN view Sec A advisory & manage own Programming class
        $this->actingAs($t1->user)->get(route('adviser.sections.show', $secA))->assertOk();
        $this->actingAs($t1->user)->get(route('teacher.classes.show', $secAProgAssignment))->assertOk();

        // Teacher 1 CANNOT view Teacher 2's advisory section (Sec B) -> 403
        $this->actingAs($t1->user)->get(route('adviser.sections.show', $secB))->assertForbidden();

        // Teacher 1 (even though Adviser of Sec A!) CANNOT manage Teacher 2's Math class in Sec A -> 403
        $this->actingAs($t1->user)->get(route('teacher.classes.show', $secAMathAssignment))->assertForbidden();

        // Teacher 3 (Subject Teacher only) CANNOT access adviser or admin routes -> 403
        $this->actingAs($t3->user)->get(route('adviser.sections.index'))->assertForbidden();
        $this->actingAs($t3->user)->get(route('admin.assignments.index'))->assertForbidden();
    }

    public function test_cannot_assign_subject_from_mismatched_curriculum_or_grade_level(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $legacy = Curriculum::query()->where('code', 'K12-LEGACY-2015')->firstOrFail();
        $g10 = GradeLevel::query()->where('code', 'G10')->firstOrFail();

        // Map G10 to MATATAG
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g10->id,
            'curriculum_id' => $matatag->id,
        ]);

        $section = Section::query()->create(['school_year_id' => $sy->id, 'grade_level_id' => $g10->id, 'name' => 'Section A', 'capacity' => 40]);
        $teacher = $this->createTeacherViaAdmin('EMP-010', 'Elena', 'Diaz', 'elena@school.local');

        // Legacy K-12 G10 subject must be rejected for a MATATAG G10 section
        $legacySubject = Subject::query()->where('curriculum_id', $legacy->id)->where('grade_level_id', $g10->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.assignments.subject', $section), [
                'subject_id' => $legacySubject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasErrors('subject_id');
    }

    public function test_reassigning_subject_teacher_preserves_previous_assignment_as_inactive(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);

        $section = Section::query()->create(['school_year_id' => $sy->id, 'grade_level_id' => $g7->id, 'name' => 'Narra', 'capacity' => 40]);
        $math7 = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();

        $t1 = $this->createTeacherViaAdmin('EMP-101', 'First', 'Teacher', 'first@school.local');
        $t2 = $this->createTeacherViaAdmin('EMP-102', 'Second', 'Teacher', 'second@school.local');

        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $section), ['subject_id' => $math7->id, 'teacher_id' => $t1->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $section), ['subject_id' => $math7->id, 'teacher_id' => $t2->id])->assertRedirect();

        $this->assertDatabaseHas('teacher_assignments', ['section_id' => $section->id, 'subject_id' => $math7->id, 'teacher_id' => $t1->id, 'is_active' => false]);
        $this->assertDatabaseHas('teacher_assignments', ['section_id' => $section->id, 'subject_id' => $math7->id, 'teacher_id' => $t2->id, 'is_active' => true]);
    }
}
