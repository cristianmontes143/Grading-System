<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingConfiguration;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentIntervention;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimulatorInterventionsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_simulator_computes_accurate_what_if_grades_and_admin_views_interventions_and_executive_report(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $tUser = User::query()->create([
            'name' => 'Sim Teacher',
            'email' => 'sim.teacher@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $tUser->roles()->attach(Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail()->id);
        Teacher::query()->create([
            'user_id' => $tUser->id,
            'employee_number' => 'EMP-SIM1',
            'first_name' => 'Sim',
            'last_name' => 'Teacher',
            'is_active' => true,
        ]);

        // Pick any active numeric grading config and simulate 100% raw scores across all leaves
        $config = GradingConfiguration::query()
            ->with('topLevelComponents.children')
            ->where('is_active', true)
            ->where('grading_mode', '!=', GradingConfiguration::MODE_DESCRIPTIVE)
            ->firstOrFail();

        $raw = [];
        $max = [];
        foreach ($config->topLevelComponents as $comp) {
            $leaves = $comp->hasChildren() ? $comp->children : collect([$comp]);
            foreach ($leaves as $leaf) {
                $raw[$leaf->id] = 50;
                $max[$leaf->id] = 50;
            }
        }

        // Subject teacher can load and run the What-If Grade Simulator
        $this->actingAs($tUser)
            ->get(route('tools.grade-simulator'))
            ->assertOk()
            ->assertSee('DepEd Grade Calculator');

        $this->actingAs($tUser)
            ->post(route('tools.grade-simulator.compute'), [
                'config_id' => $config->id,
                'raw' => $raw,
                'max' => $max,
            ])
            ->assertOk()
            ->assertSee('100.00')
            ->assertSee('Simulated Official Result');

        // Seed an overdue intervention and verify Admin School-Wide Interventions Tracker + Executive Report
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy);
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
            'name' => 'Opal',
            'capacity' => 40,
        ]);
        $student = Student::query()->create([
            'student_number' => '2026-7101',
            'lrn' => '100000007101',
            'first_name' => 'Bea',
            'last_name' => 'Navarro',
            'gender' => 'Female',
            'date_of_birth' => '2013-04-04',
            'status' => Student::STATUS_ACTIVE,
        ]);
        $enr = app(EnrollmentService::class)->enrollStudent($student, $section);

        StudentIntervention::query()->create([
            'student_enrollment_id' => $enr->id,
            'category' => StudentIntervention::CATEGORY_PARENT_CONFERENCE,
            'notes' => 'Follow-up reading conference needed.',
            'follow_up_date' => now()->subDays(3)->toDateString(),
            'status' => StudentIntervention::STATUS_OPEN,
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.interventions.index'))
            ->assertOk()
            ->assertSee('Navarro, Bea')
            ->assertSee('Overdue');

        $this->actingAs($admin)
            ->get(route('admin.analytics.print', ['school_year_id' => $sy->id]))
            ->assertOk()
            ->assertSee('SCHOOL-WIDE EXECUTIVE ACADEMIC PERFORMANCE');

        // Subject teacher blocked from Admin Interventions & Executive Report
        $this->actingAs($tUser)->get(route('admin.interventions.index'))->assertForbidden();
        $this->actingAs($tUser)->get(route('admin.analytics.print'))->assertForbidden();
    }
}
