<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Curriculum;
use App\Models\DatabaseBackup;
use App\Models\GradeLevel;
use App\Models\GradingConfiguration;
use App\Models\GradingPeriod;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TransmutationTable;
use App\Models\User;
use App\Services\Grading\GradebookService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module9EndToEndWorkflowTest extends TestCase
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

    public function test_complete_13_step_school_grading_workflow_across_all_three_roles(): void
    {
        // STEP 1: Administrator creates the active School Year & opens First Term
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [
                'name' => '2026-2027',
                'start_date' => '2026-06-15',
                'end_date' => '2027-03-31',
                'term_structure' => 'trimester',
                'set_active' => '1',
                'generate_periods' => '1',
            ])
            ->assertRedirect(route('admin.school-years.index'));

        $sy = SchoolYear::query()->where('name', '2026-2027')->firstOrFail();
        $t1 = $sy->gradingPeriods()->where('code', 'T1')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.grading-periods.open', $t1))->assertRedirect();

        // STEP 2: Administrator maps MATATAG Curriculum & creates Section
        $sshs = Curriculum::query()->where('code', 'SSHS-2026')->firstOrFail();
        $g12 = GradeLevel::query()->where('code', 'G12')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.curricula.implementations.save'), [
                'school_year_id' => $sy->id,
                'mappings' => [
                    $g12->id => ['curriculum_id' => $sshs->id, 'implementation_status' => 'active'],
                ],
            ])
            ->assertRedirect();

        $math = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'MATH12', 'name' => 'Mathematics', 'weight_group' => 'shs_core']);
        $prog = Subject::query()->create(['curriculum_id' => $sshs->id, 'grade_level_id' => $g12->id, 'code' => 'PROG12', 'name' => 'Programming', 'weight_group' => 'shs_techpro']);

        $this->actingAs($this->admin)
            ->post(route('admin.sections.store'), [
                'school_year_id' => $sy->id,
                'grade_level_id' => $g12->id,
                'name' => 'Section A',
                'room' => 'Room 301',
                'capacity' => 40,
            ])
            ->assertRedirect();
        $secA = Section::query()->where('name', 'Section A')->firstOrFail();

        // STEP 3: Administrator registers Teachers & Students
        $this->actingAs($this->admin)->post(route('admin.teachers.store'), [
            'employee_number' => 'T-001',
            'first_name' => 'Elena',
            'last_name' => 'Ramos',
            'email' => 'elena.ramos@school.local',
            'password' => 'Teacher@2026',
            'role_name' => Role::ADVISER,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.teachers.store'), [
            'employee_number' => 'T-002',
            'first_name' => 'Marco',
            'last_name' => 'Villanueva',
            'email' => 'marco.v@school.local',
            'password' => 'Teacher@2026',
            'role_name' => Role::SUBJECT_TEACHER,
        ])->assertRedirect();

        $adviser = Teacher::query()->with('user')->where('employee_number', 'T-001')->firstOrFail();
        $mathTeacher = Teacher::query()->with('user')->where('employee_number', 'T-002')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.students.store'), [
            'student_number' => '2026-0101',
            'lrn' => '112233445566',
            'first_name' => 'Liza',
            'last_name' => 'Soriano',
            'gender' => 'Female',
            'date_of_birth' => '2009-04-10',
            'section_id' => $secA->id,
        ])->assertRedirect();

        $student = Student::query()->where('student_number', '2026-0101')->firstOrFail();
        $enrollment = $student->currentEnrollment()->firstOrFail();

        // STEP 4: Administrator assigns Adviser (Elena) + Subject Teachers (Marco -> Math, Elena -> Programming)
        $this->actingAs($this->admin)->post(route('admin.assignments.adviser', $secA), ['adviser_id' => $adviser->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secA), ['subject_id' => $math->id, 'teacher_id' => $mathTeacher->id])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.assignments.subject', $secA), ['subject_id' => $prog->id, 'teacher_id' => $adviser->id])->assertRedirect();

        $mathAssign = TeacherAssignment::query()->where('section_id', $secA->id)->where('subject_id', $math->id)->firstOrFail();
        $progAssign = TeacherAssignment::query()->where('section_id', $secA->id)->where('subject_id', $prog->id)->firstOrFail();

        // STEP 5: Administrator confirms grading policy & transmutation table
        $table = TransmutationTable::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.transmutation-tables.confirm', $table))->assertRedirect();
        $policy = GradingConfiguration::query()->where('curriculum_id', $sshs->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.grading-policies.confirm', $policy))->assertRedirect();

        // STEPS 6-9: Both Subject Teachers log in, encode assessments/scores, auto-compute, and submit!
        foreach ([[$mathTeacher, $mathAssign, 91], [$adviser, $progAssign, 95]] as [$teacher, $assign, $score]) {
            $this->actingAs($teacher->user)->get(route('dashboard'))->assertOk();
            $gb = app(GradebookService::class);
            $config = $gb->resolveConfiguration($assign);

            $scorePayload = [];
            foreach ($gb->getLeafComponents($config) as $leaf) {
                $this->actingAs($teacher->user)
                    ->post(route('teacher.gradebook.assessments.store', [$assign, $t1]), [
                        'grading_component_id' => $leaf['id'],
                        'assessment_type' => 'quiz',
                        'title' => "Activity {$leaf['code']}",
                        'max_score' => 100,
                    ])
                    ->assertRedirect();

                $a = Assessment::query()
                    ->where('teacher_assignment_id', $assign->id)
                    ->where('grading_component_id', $leaf['id'])
                    ->firstOrFail();
                $scorePayload[$enrollment->id][$a->id] = $score;
            }

            $this->actingAs($teacher->user)
                ->post(route('teacher.gradebook.scores.save', [$assign, $t1]), ['scores' => $scorePayload])
                ->assertRedirect();

            $this->actingAs($teacher->user)
                ->post(route('teacher.gradebook.submit', [$assign, $t1]))
                ->assertRedirect();
        }

        // STEPS 10-12: Adviser sees 100% submitted automatically, views Student Performance Summary & generates Official Individual + Batch PDF Report Cards!
        $this->actingAs($adviser->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ready for Report Cards');

        $this->actingAs($adviser->user)
            ->get(route('adviser.students.summary', ['enrollment' => $enrollment, 'period_id' => $t1->id]))
            ->assertOk()
            ->assertSee('Soriano, Liza')
            ->assertSee('Mathematics')
            ->assertSee('Programming');

        // Admin can also inspect any section's grades & student performance summaries
        $this->actingAs($this->admin)
            ->get(route('adviser.sections.show', $secA))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('adviser.students.summary', $enrollment))
            ->assertOk();

        $pdfIndividual = $this->actingAs($adviser->user)
            ->get(route('adviser.report-cards.individual', ['enrollment' => $enrollment, 'period_id' => $t1->id]));
        $pdfIndividual->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfIndividual->getContent());

        $pdfBatch = $this->actingAs($adviser->user)
            ->get(route('adviser.report-cards.batch', ['section' => $secA, 'period_id' => $t1->id]));
        $pdfBatch->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdfBatch->getContent());

        // STEP 13: Administrator monitors dashboard, views submissions & audit logs, and creates a verified PostgreSQL backup!
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('100%');

        $this->actingAs($this->admin)
            ->get(route('admin.submissions.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.backups.store'), ['notes' => 'End-of-Term 1 verified backup'])
            ->assertRedirect(route('admin.backups.index'));

        $backup = DatabaseBackup::query()->latest()->firstOrFail();
        $this->assertGreaterThan(0, $backup->size_bytes);

        $this->actingAs($this->admin)
            ->get(route('admin.backups.download', $backup))
            ->assertOk();

        // Clean up test backup file on disk
        $this->actingAs($this->admin)
            ->delete(route('admin.backups.destroy', $backup))
            ->assertRedirect(route('admin.backups.index'));

        // Non-admin teachers cannot access backups -> 403
        $this->actingAs($adviser->user)->get(route('admin.backups.index'))->assertForbidden();
        $this->actingAs($mathTeacher->user)->get(route('admin.backups.index'))->assertForbidden();
    }
}
