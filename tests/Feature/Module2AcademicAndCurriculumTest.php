<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\PolicyReference;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\ShsTrack;
use App\Models\User;
use App\Services\Curriculum\CurriculumResolverService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module2AcademicAndCurriculumTest extends TestCase
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

    public function test_admin_can_create_school_year_with_auto_trimester_periods_and_single_active_enforcement(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [
                'name' => '2025-2026',
                'start_date' => '2025-06-16',
                'end_date' => '2026-03-31',
                'term_structure' => 'quarterly',
                'set_active' => '1',
                'generate_periods' => '1',
            ])
            ->assertRedirect(route('admin.school-years.index'));

        $sy2025 = SchoolYear::query()->where('name', '2025-2026')->firstOrFail();
        $this->assertTrue($sy2025->is_active);
        $this->assertCount(4, $sy2025->gradingPeriods);

        // Creating a second active SY deactivates the first (PostgreSQL partial unique index preserved)
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

        $sy2026 = SchoolYear::query()->where('name', '2026-2027')->firstOrFail();
        $this->assertFalse($sy2025->fresh()->is_active);
        $this->assertTrue($sy2026->is_active);
        $this->assertCount(3, $sy2026->gradingPeriods);
        $this->assertEquals(['T1', 'T2', 'T3'], $sy2026->gradingPeriods->pluck('code')->all());
    }

    public function test_grading_period_open_lock_and_audited_unlock_lifecycle(): void
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

        // Open period
        $this->actingAs($this->admin)
            ->post(route('admin.grading-periods.open', $t1))
            ->assertRedirect(route('admin.school-years.index'));
        $this->assertSame(GradingPeriod::STATUS_OPEN, $t1->fresh()->status);

        // Lock period
        $this->actingAs($this->admin)
            ->post(route('admin.grading-periods.lock', $t1))
            ->assertRedirect(route('admin.school-years.index'));
        $this->assertSame(GradingPeriod::STATUS_LOCKED, $t1->fresh()->status);
        $this->assertNotNull($t1->fresh()->locked_at);

        // Unlock without reason fails validation
        $this->actingAs($this->admin)
            ->post(route('admin.grading-periods.unlock', $t1), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        // Unlock with valid reason succeeds and writes audit log
        $this->actingAs($this->admin)
            ->post(route('admin.grading-periods.unlock', $t1), [
                'reason' => 'Approved deadline extension for Math teachers',
            ])
            ->assertRedirect(route('admin.school-years.index'));

        $this->assertSame(GradingPeriod::STATUS_OPEN, $t1->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'grading_period.unlocked',
            'reason' => 'Approved deadline extension for Math teachers',
        ]);
    }

    public function test_phased_curriculum_transition_never_mixes_old_and_new_curriculum_subjects(): void
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
        $g1 = GradeLevel::query()->where('code', 'G1')->firstOrFail();
        $g10 = GradeLevel::query()->where('code', 'G10')->firstOrFail();

        // Assign Grade 1 -> MATATAG, Grade 10 -> Legacy K-12 in SY 2026-2027
        $this->actingAs($this->admin)
            ->post(route('admin.curricula.implementations.save'), [
                'school_year_id' => $sy->id,
                'mappings' => [
                    $g1->id => ['curriculum_id' => $matatag->id, 'implementation_status' => 'active'],
                    $g10->id => ['curriculum_id' => $legacy->id, 'implementation_status' => 'phasing_out'],
                ],
            ])
            ->assertRedirect(route('admin.curricula.index', ['school_year_id' => $sy->id]));

        $secG1 = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g1->id,
            'name' => 'Section Sampaguita',
            'capacity' => 35,
        ]);

        $secG10 = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g10->id,
            'name' => 'Section Rizal',
            'capacity' => 45,
        ]);

        $resolver = app(CurriculumResolverService::class);
        $g1Subjects = $resolver->getOfferingsForSection($secG1)->pluck('code')->all();
        $g10Subjects = $resolver->getOfferingsForSection($secG10)->pluck('code')->all();

        // Grade 1 MATATAG streamlined 5 learning areas
        $this->assertEqualsCanonicalizing(['LANG1', 'READ1', 'MATH1', 'MAKA1', 'GMRC1'], $g1Subjects);

        // Grade 10 uses Legacy K-12 subjects (EsP instead of MATATAG VE) and never mixes MATATAG G10 subjects
        $this->assertContains('ESP10-K12', $g10Subjects);
        $this->assertNotContains('VE10', $g10Subjects);
    }

    public function test_policy_reference_starts_unconfirmed_and_can_be_confirmed_by_admin(): void
    {
        $policy = PolicyReference::query()->where('document_number', 'DO 015, s. 2026')->firstOrFail();
        $this->assertFalse($policy->is_confirmed_by_admin);

        $this->actingAs($this->admin)
            ->post(route('admin.policies.confirm', $policy))
            ->assertRedirect(route('admin.policies.index'));

        $this->assertTrue($policy->fresh()->is_confirmed_by_admin);
        $this->assertSame($this->admin->id, $policy->fresh()->confirmed_by);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'policy_reference.confirmed',
            'actor_user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_create_shs_section_and_subjects_while_non_admins_are_forbidden(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);

        $sshs = Curriculum::query()->where('code', 'SSHS-2026')->firstOrFail();
        $g11 = GradeLevel::query()->where('code', 'G11')->firstOrFail();
        $techPro = ShsTrack::query()->where('curriculum_id', $sshs->id)->where('code', 'TECHPRO')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.sections.store'), [
                'school_year_id' => $sy->id,
                'grade_level_id' => $g11->id,
                'shs_track_id' => $techPro->id,
                'name' => 'Grade 11 TechPro A',
                'room' => 'ICT Lab 1',
                'capacity' => 40,
            ])
            ->assertRedirect(route('admin.sections.index', ['school_year_id' => $sy->id]));

        $this->assertDatabaseHas('sections', [
            'school_year_id' => $sy->id,
            'grade_level_id' => $g11->id,
            'shs_track_id' => $techPro->id,
            'name' => 'Grade 11 TechPro A',
        ]);

        // Non-admin subject teacher cannot access any Module 2 routes
        $teacherRole = Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail();
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->roles()->attach($teacherRole->id);

        $this->actingAs($teacher)->get(route('admin.school-years.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.curricula.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.policies.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.subjects.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.sections.index'))->assertForbidden();
    }
}
