<?php

namespace Tests\Unit;

use App\Models\GradingComponent;
use App\Models\GradingConfiguration;
use App\Models\TransmutationTable;
use App\Services\Grading\GradeCalculationService;
use App\Services\Grading\TransmutationService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GradeCalculationService $calculator;
    private TransmutationService $transmuter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $this->transmuter = app(TransmutationService::class);
        $this->calculator = app(GradeCalculationService::class);
    }

    public function test_do8_standard_transmutation_table_boundary_accuracy(): void
    {
        $table = TransmutationTable::query()
            ->with('rows')
            ->where('name', 'like', '%DO 8, s. 2015%')
            ->firstOrFail();

        $this->assertSame(60.0, $this->transmuter->transmute($table, 0.00));
        $this->assertSame(74.0, $this->transmuter->transmute($table, 59.99));
        $this->assertSame(75.0, $this->transmuter->transmute($table, 60.00));
        $this->assertSame(85.0, $this->transmuter->transmute($table, 76.00));
        $this->assertSame(90.0, $this->transmuter->transmute($table, 84.00));
        $this->assertSame(99.0, $this->transmuter->transmute($table, 99.99));
        $this->assertSame(100.0, $this->transmuter->transmute($table, 100.00));
    }

    public function test_do8_flat_ww_pt_qa_computation_with_excused_and_missing_scores(): void
    {
        // Legacy K-12 Languages (30 / 50 / 20) + DO 8 Transmutation
        $config = GradingConfiguration::query()
            ->with(['topLevelComponents.children', 'transmutationTable.rows'])
            ->where('name', 'like', '%Legacy K-12 Languages%')
            ->firstOrFail();

        $ww = $config->topLevelComponents->firstWhere('code', 'WW');
        $pt = $config->topLevelComponents->firstWhere('code', 'PT');
        $qa = $config->topLevelComponents->firstWhere('code', 'QA');

        // WW: 18/20 + 22/30 + excused 10 -> Total 40/50 = 80.00% * 30% = 24.00
        // PT: 45/50 + missing (0/50) -> Total 45/100 = 45.00% * 50% = 22.50 (missing_count = 1)
        // QA: 35/50 = 70.00% * 20% = 14.00
        // Initial Grade = 24.00 + 22.50 + 14.00 = 60.50 -> DO 8 Transmuted = 75
        $result = $this->calculator->computePeriodGrade($config, [
            $ww->id => [
                ['raw_score' => 18, 'max_score' => 20, 'status' => 'scored'],
                ['raw_score' => 22, 'max_score' => 30, 'status' => 'scored'],
                ['raw_score' => null, 'max_score' => 10, 'status' => 'excused'],
            ],
            $pt->id => [
                ['raw_score' => 45, 'max_score' => 50, 'status' => 'scored'],
                ['raw_score' => null, 'max_score' => 50, 'status' => 'missing'],
            ],
            $qa->id => [
                ['raw_score' => 35, 'max_score' => 50, 'status' => 'scored'],
            ],
        ]);

        $this->assertSame(60.5, $result['initial_grade']);
        $this->assertSame(75.0, $result['transmuted_grade']);
        $this->assertSame(75.0, $result['final_grade']);
        $this->assertSame(1, $result['missing_count']);
        $this->assertSame('Fairly Satisfactory', $result['remarks']);
    }

    public function test_do15_nested_examination_sub_components_and_zero_based_mode(): void
    {
        // MATATAG Core (20 / 50 / 30 with EX = ST1 30% + ST2 30% + TE 40%)
        $config = GradingConfiguration::query()
            ->with(['topLevelComponents.children', 'transmutationTable.rows'])
            ->where('name', 'like', '%MATATAG Mathematics & Science%')
            ->firstOrFail();

        $ww = $config->topLevelComponents->firstWhere('code', 'WW');
        $pt = $config->topLevelComponents->firstWhere('code', 'PT');
        $ex = $config->topLevelComponents->firstWhere('code', 'EX');
        $st1 = $ex->children->firstWhere('code', 'ST1');
        $st2 = $ex->children->firstWhere('code', 'ST2');
        $te = $ex->children->firstWhere('code', 'TE');

        // WW: 80/100 = 80% * 20% = 16.00
        // PT: 90/100 = 90% * 50% = 45.00
        // EX: ST1=80% (24) + ST2=90% (27) + TE=85% (34) => EX PS = 85.00% * 30% = 25.50
        // Initial Grade = 16 + 45 + 25.5 = 86.50
        $scores = [
            $ww->id => [['raw_score' => 80, 'max_score' => 100, 'status' => 'scored']],
            $pt->id => [['raw_score' => 90, 'max_score' => 100, 'status' => 'scored']],
            $st1->id => [['raw_score' => 40, 'max_score' => 50, 'status' => 'scored']],
            $st2->id => [['raw_score' => 45, 'max_score' => 50, 'status' => 'scored']],
            $te->id => [['raw_score' => 85, 'max_score' => 100, 'status' => 'scored']],
        ];

        // Transition transmuted (SY 2026-27)
        $transmutedResult = $this->calculator->computePeriodGrade($config, $scores);
        $this->assertSame(86.5, $transmutedResult['initial_grade']);
        $this->assertNotNull($transmutedResult['transmuted_grade']);
        $this->assertGreaterThanOrEqual(88.0, $transmutedResult['final_grade']);

        // Switch to Zero-Based Direct Grading (SY 2027-28+)
        $config->grading_mode = GradingConfiguration::MODE_NUMERICAL_ZERO_BASED;
        $zeroBasedResult = $this->calculator->computePeriodGrade($config, $scores);
        $this->assertSame(86.5, $zeroBasedResult['initial_grade']);
        $this->assertNull($zeroBasedResult['transmuted_grade']);
        $this->assertSame(87.0, $zeroBasedResult['final_grade']);
    }

    public function test_key_stage_1_descriptive_grading_and_general_average_promotion_rules(): void
    {
        $ks1 = GradingConfiguration::query()
            ->where('grading_mode', GradingConfiguration::MODE_DESCRIPTIVE)
            ->firstOrFail();

        $desc = $this->calculator->computePeriodGrade($ks1, [], 'CO');
        $this->assertNull($desc['final_grade']);
        $this->assertSame('CO', $desc['descriptive_rating']);
        $this->assertStringContainsString('Consistent', $desc['remarks']);

        // Final subject grade across 3 trimesters: (85 + 88 + 90) / 3 = 87.67 -> 88
        $this->assertSame(88.0, $this->calculator->computeFinalSubjectGrade([85, 88, 90]));

        // Promotion status checks
        $promoted = $this->calculator->computeGeneralAverageAndStatus([88, 90, 85, 84]);
        $this->assertSame(87.0, $promoted['general_average']);
        $this->assertSame('promoted', $promoted['promotion_status']);

        $conditional = $this->calculator->computeGeneralAverageAndStatus([85, 73, 74, 82]);
        $this->assertSame('conditional', $conditional['promotion_status']);
        $this->assertSame(2, $conditional['failed_count']);

        $retained = $this->calculator->computeGeneralAverageAndStatus([70, 72, 74, 80]);
        $this->assertSame('retained', $retained['promotion_status']);

        $incomplete = $this->calculator->computeGeneralAverageAndStatus([85, null, 90]);
        $this->assertSame('incomplete', $incomplete['promotion_status']);
        $this->assertNull($incomplete['general_average']);
    }
}
