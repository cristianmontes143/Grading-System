<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\GradeLevel;
use App\Models\GradingConfiguration;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\TransmutationTable;
use App\Models\User;
use App\Services\Grading\GradingConfigurationResolver;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module5GradingPolicyTest extends TestCase
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

    public function test_admin_cannot_save_grading_configuration_when_weights_do_not_total_100(): void
    {
        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.grading-policies.store'), [
                'name' => 'Invalid Weights Policy',
                'curriculum_id' => $matatag->id,
                'grading_mode' => GradingConfiguration::MODE_NUMERICAL_ZERO_BASED,
                'passing_grade' => 75,
                'rounding_precision' => 0,
                'components' => [
                    ['code' => 'WW', 'name' => 'Written Work', 'weight_percentage' => 30],
                    ['code' => 'PT', 'name' => 'Performance Tasks', 'weight_percentage' => 50],
                ],
            ])
            ->assertSessionHasErrors('components');
    }

    public function test_admin_can_create_subject_override_and_resolver_picks_most_specific_configuration(): void
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
        $math7 = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();
        $do8Table = TransmutationTable::query()->where('name', 'like', '%DO 8, s. 2015%')->firstOrFail();

        // Create a SY + Subject specific override (35 / 45 / 20)
        $this->actingAs($this->admin)
            ->post(route('admin.grading-policies.store'), [
                'name' => 'SY 2026-27 Grade 7 Math Custom Override',
                'school_year_id' => $sy->id,
                'curriculum_id' => $matatag->id,
                'grade_level_id' => $g7->id,
                'subject_id' => $math7->id,
                'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                'transmutation_table_id' => $do8Table->id,
                'passing_grade' => 75,
                'rounding_precision' => 0,
                'components' => [
                    ['code' => 'WW', 'name' => 'Written Work', 'weight_percentage' => 35],
                    ['code' => 'PT', 'name' => 'Performance Tasks', 'weight_percentage' => 45],
                    ['code' => 'QA', 'name' => 'Quarterly Assessment', 'weight_percentage' => 20],
                ],
            ])
            ->assertRedirect(route('admin.grading-policies.index'));

        $resolved = app(GradingConfigurationResolver::class)->resolve($sy->id, $matatag->id, $g7->id, $math7);
        $this->assertNotNull($resolved);
        $this->assertSame('SY 2026-27 Grade 7 Math Custom Override', $resolved->name);
    }

    public function test_admin_can_confirm_transmutation_table_and_non_admins_are_forbidden(): void
    {
        $table = TransmutationTable::query()->firstOrFail();
        $this->assertFalse($table->is_confirmed_by_admin);

        $this->actingAs($this->admin)
            ->post(route('admin.transmutation-tables.confirm', $table))
            ->assertRedirect();

        $this->assertTrue($table->fresh()->is_confirmed_by_admin);

        $teacherRole = Role::query()->where('name', Role::ADVISER)->firstOrFail();
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->roles()->attach($teacherRole->id);

        $this->actingAs($teacher)->get(route('admin.grading-policies.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.transmutation-tables.index'))->assertForbidden();
    }
}
