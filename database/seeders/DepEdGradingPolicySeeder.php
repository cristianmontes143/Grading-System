<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\GradeLevel;
use App\Models\GradingComponent;
use App\Models\GradingConfiguration;
use App\Models\PolicyReference;
use App\Models\TransmutationTable;
use App\Models\TransmutationTableRow;
use Illuminate\Database\Seeder;

class DepEdGradingPolicySeeder extends Seeder
{
    public function run(): void
    {
        $do8 = PolicyReference::query()->where('document_number', 'DO 8, s. 2015')->first();
        $do15 = PolicyReference::query()->where('document_number', 'DO 015, s. 2026')->first();

        // 1. Official DO 8, s. 2015 Appendix B Transmutation Table (41 rows; IG 60.00 -> 75, 0 -> 60)
        $do8Table = TransmutationTable::query()->updateOrCreate(
            ['name' => 'DepEd DO 8, s. 2015 Standard Transmutation Table (60 -> 75)'],
            [
                'policy_reference_id' => $do8?->id,
                'passing_initial_grade' => 60.00,
                'passing_transmuted_grade' => 75.00,
                'is_active' => true,
                'notes' => 'Official Appendix B Transmutation Table from DO 8, s. 2015. Retained for historical K-12 records and schools still using the 4-quarter DO 8 framework. Confirm before activating.',
            ]
        );

        $this->seedRowsIfEmpty($do8Table, [
            [100.00, 100.00, 100],
            [98.40, 99.99, 99], [96.80, 98.39, 98], [95.20, 96.79, 97], [93.60, 95.19, 96], [92.00, 93.59, 95],
            [90.40, 91.99, 94], [88.80, 90.39, 93], [87.20, 88.79, 92], [85.60, 87.19, 91], [84.00, 85.59, 90],
            [82.40, 83.99, 89], [80.80, 82.39, 88], [79.20, 80.79, 87], [77.60, 79.19, 86], [76.00, 77.59, 85],
            [74.40, 75.99, 84], [72.80, 74.39, 83], [71.20, 72.79, 82], [69.60, 71.19, 81], [68.00, 69.59, 80],
            [66.40, 67.99, 79], [64.80, 66.39, 78], [63.20, 64.79, 77], [61.60, 63.19, 76], [60.00, 61.59, 75],
            [56.00, 59.99, 74], [52.00, 55.99, 73], [48.00, 51.99, 72], [44.00, 47.99, 71], [40.00, 43.99, 70],
            [36.00, 39.99, 69], [32.00, 35.99, 68], [28.00, 31.99, 67], [24.00, 27.99, 66], [20.00, 23.99, 65],
            [16.00, 19.99, 64], [12.00, 15.99, 63], [8.00, 11.99, 62],  [4.00, 7.99, 61],   [0.00, 3.99, 60],
        ]);

        // 2. DO 015, s. 2026 SY 2026-2027 Adjusted Transition Transmutation Table (IG 70.00 -> 75, editable by Admin)
        $do15TransitionTable = TransmutationTable::query()->updateOrCreate(
            ['name' => 'DepEd DO 015, s. 2026 Transition Transmutation Table (SY 2026-27: 70 -> 75)'],
            [
                'policy_reference_id' => $do15?->id,
                'passing_initial_grade' => 70.00,
                'passing_transmuted_grade' => 75.00,
                'is_active' => true,
                'notes' => 'Configurable SY 2026-2027 transition table preset (70.00 -> 75) ahead of full zero-based grading in SY 2027-2028. Administrator MUST review and confirm exact brackets against the official DO 015, s. 2026 Annex.',
            ]
        );

        // Linear transition intervals: [70..100] -> [75..100] (step 1.20), [0..69.99] -> [60..74] (step ~4.67)
        $transitionRows = [[100.00, 100.00, 100]];
        for ($t = 99; $t >= 75; $t--) {
            $min = round(70.00 + (($t - 75) * 1.20), 2);
            $max = round($min + 1.19, 2);
            if ($t === 99) {
                $max = 99.99;
            }
            $transitionRows[] = [$min, $max, $t];
        }
        $stepFail = 70.00 / 15.0; // 15 brackets (60..74)
        for ($t = 74; $t >= 60; $t--) {
            $idx = $t - 60;
            $min = $t === 60 ? 0.00 : round($idx * $stepFail, 2);
            $max = $t === 74 ? 69.99 : round((($idx + 1) * $stepFail) - 0.01, 2);
            $transitionRows[] = [$min, $max, $t];
        }
        $this->seedRowsIfEmpty($do15TransitionTable, $transitionRows);

        // Standard DepEd Remarks Scales (configurable per policy)
        $do8Remarks = [
            ['min' => 90, 'max' => 100, 'label' => 'Outstanding'],
            ['min' => 85, 'max' => 89.99, 'label' => 'Very Satisfactory'],
            ['min' => 80, 'max' => 84.99, 'label' => 'Satisfactory'],
            ['min' => 75, 'max' => 79.99, 'label' => 'Fairly Satisfactory'],
            ['min' => 0,  'max' => 74.99, 'label' => 'Did Not Meet Expectations'],
        ];

        $ks1DescriptiveScale = [
            ['code' => 'CO', 'label' => 'Consistent (Palagiang Naipapakita)'],
            ['code' => 'DV', 'label' => 'Developing (Umuusbong)'],
            ['code' => 'BG', 'label' => 'Beginning (Nagsisimula)'],
        ];

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->first();
        $legacyK12 = Curriculum::query()->where('code', 'K12-LEGACY-2015')->first();
        $sshs = Curriculum::query()->where('code', 'SSHS-2026')->first();
        $legacyShs = Curriculum::query()->where('code', 'SHS-LEGACY-2019')->first();
        $kinder = GradeLevel::query()->where('code', 'K')->first();
        $grade1 = GradeLevel::query()->where('code', 'G1')->first();

        // 3. MATATAG K-10 Grading Configuration Presets (DO 015, s. 2026 — flagged for Admin confirmation)
        if ($matatag) {
            // Key Stage 1 Descriptive (Kindergarten & Grade 1 starting SY 2026-27)
            foreach (array_filter([$kinder, $grade1]) as $ks1Grade) {
                GradingConfiguration::query()->firstOrCreate(
                    [
                        'curriculum_id' => $matatag->id,
                        'grade_level_id' => $ks1Grade->id,
                        'weight_group' => null,
                        'school_year_id' => null,
                    ],
                    [
                        'name' => "MATATAG {$ks1Grade->name} Descriptive Grading (DO 015, s. 2026)",
                        'grading_mode' => GradingConfiguration::MODE_DESCRIPTIVE,
                        'passing_grade' => 75.00,
                        'rounding_precision' => 0,
                        'descriptive_scale_json' => $ks1DescriptiveScale,
                        'policy_reference_id' => $do15?->id,
                        'is_confirmed_by_admin' => false,
                        'is_active' => true,
                    ]
                );
            }

            // G4-G10 Core Academic (Languages, Math, Science, AP, GMRC/VE): WW 20% / PT 50% / EX 30% (ST1 30, ST2 30, TE 40)
            foreach (['languages_ap_gmrc' => 'Languages, AP, GMRC / VE', 'math_science' => 'Mathematics & Science'] as $wg => $label) {
                $cfg = GradingConfiguration::query()->firstOrCreate(
                    [
                        'curriculum_id' => $matatag->id,
                        'weight_group' => $wg,
                        'grade_level_id' => null,
                        'school_year_id' => null,
                    ],
                    [
                        'name' => "MATATAG {$label} (DO 015, s. 2026: 20/50/30)",
                        'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                        'transmutation_table_id' => $do15TransitionTable->id,
                        'passing_grade' => 75.00,
                        'rounding_precision' => 0,
                        'remarks_scale_json' => $do8Remarks,
                        'policy_reference_id' => $do15?->id,
                        'is_confirmed_by_admin' => false,
                        'is_active' => true,
                    ]
                );
                $this->seedDo15ComponentsIfEmpty($cfg, 20, 50, 30);
            }

            // G4-G10 MAPEH & EPP/TLE: WW 20% / PT 60% / EX 20% (ST1 30, ST2 30, TE 40)
            $mapehCfg = GradingConfiguration::query()->firstOrCreate(
                [
                    'curriculum_id' => $matatag->id,
                    'weight_group' => 'mapeh_epp_tle',
                    'grade_level_id' => null,
                    'school_year_id' => null,
                ],
                [
                    'name' => 'MATATAG MAPEH / EPP / TLE (DO 015, s. 2026: 20/60/20)',
                    'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                    'transmutation_table_id' => $do15TransitionTable->id,
                    'passing_grade' => 75.00,
                    'rounding_precision' => 0,
                    'remarks_scale_json' => $do8Remarks,
                    'policy_reference_id' => $do15?->id,
                    'is_confirmed_by_admin' => false,
                    'is_active' => true,
                ]
            );
            $this->seedDo15ComponentsIfEmpty($mapehCfg, 20, 60, 20);
        }

        // 4. Legacy K-12 Presets (DO 8, s. 2015: flat WW/PT/QA + Standard Transmutation 60->75)
        if ($legacyK12) {
            foreach ([
                ['languages_ap_gmrc', 'Legacy K-12 Languages / AP / EsP (DO 8: 30/50/20)', 30, 50, 20],
                ['math_science', 'Legacy K-12 Math & Science (DO 8: 40/40/20)', 40, 40, 20],
                ['mapeh_epp_tle', 'Legacy K-12 MAPEH / EPP / TLE (DO 8: 20/60/20)', 20, 60, 20],
            ] as [$wg, $name, $ww, $pt, $qa]) {
                $cfg = GradingConfiguration::query()->firstOrCreate(
                    [
                        'curriculum_id' => $legacyK12->id,
                        'weight_group' => $wg,
                        'grade_level_id' => null,
                        'school_year_id' => null,
                    ],
                    [
                        'name' => $name,
                        'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                        'transmutation_table_id' => $do8Table->id,
                        'passing_grade' => 75.00,
                        'rounding_precision' => 0,
                        'remarks_scale_json' => $do8Remarks,
                        'policy_reference_id' => $do8?->id,
                        'is_confirmed_by_admin' => false,
                        'is_active' => true,
                    ]
                );
                $this->seedFlatComponentsIfEmpty($cfg, [
                    ['WW', 'Written Work', $ww, 10],
                    ['PT', 'Performance Tasks', $pt, 20],
                    ['QA', 'Quarterly Assessment', $qa, 30],
                ]);
            }
        }

        // 5. Strengthened SHS Presets (DO 015, s. 2026)
        if ($sshs) {
            $shsCore = GradingConfiguration::query()->firstOrCreate(
                ['curriculum_id' => $sshs->id, 'weight_group' => 'shs_core', 'grade_level_id' => null, 'school_year_id' => null],
                [
                    'name' => 'Strengthened SHS Core & Academic Electives (DO 015: 20/50/30)',
                    'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                    'transmutation_table_id' => $do15TransitionTable->id,
                    'passing_grade' => 75.00,
                    'rounding_precision' => 0,
                    'remarks_scale_json' => $do8Remarks,
                    'policy_reference_id' => $do15?->id,
                    'is_confirmed_by_admin' => false,
                    'is_active' => true,
                ]
            );
            $this->seedDo15ComponentsIfEmpty($shsCore, 20, 50, 30);

            $shsTechPro = GradingConfiguration::query()->firstOrCreate(
                ['curriculum_id' => $sshs->id, 'weight_group' => 'shs_techpro', 'grade_level_id' => null, 'school_year_id' => null],
                [
                    'name' => 'Strengthened SHS TechPro Electives (DO 015: 15/65/20)',
                    'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                    'transmutation_table_id' => $do15TransitionTable->id,
                    'passing_grade' => 75.00,
                    'rounding_precision' => 0,
                    'remarks_scale_json' => $do8Remarks,
                    'policy_reference_id' => $do15?->id,
                    'is_confirmed_by_admin' => false,
                    'is_active' => true,
                ]
            );
            $this->seedDo15ComponentsIfEmpty($shsTechPro, 15, 65, 20);

            $shsResearch = GradingConfiguration::query()->firstOrCreate(
                ['curriculum_id' => $sshs->id, 'weight_group' => 'shs_research', 'grade_level_id' => null, 'school_year_id' => null],
                [
                    'name' => 'Strengthened SHS Research & Innovation (DO 015: 40/60)',
                    'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                    'transmutation_table_id' => $do15TransitionTable->id,
                    'passing_grade' => 75.00,
                    'rounding_precision' => 0,
                    'remarks_scale_json' => $do8Remarks,
                    'policy_reference_id' => $do15?->id,
                    'is_confirmed_by_admin' => false,
                    'is_active' => true,
                ]
            );
            $this->seedFlatComponentsIfEmpty($shsResearch, [
                ['WW', 'Written / Oral Works', 40, 10],
                ['PT', 'Performance Tasks', 60, 20],
            ]);

            $shsImmersion = GradingConfiguration::query()->firstOrCreate(
                ['curriculum_id' => $sshs->id, 'weight_group' => 'shs_immersion', 'grade_level_id' => null, 'school_year_id' => null],
                [
                    'name' => 'Strengthened SHS Work Immersion (DO 015: 20/80)',
                    'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                    'transmutation_table_id' => $do15TransitionTable->id,
                    'passing_grade' => 75.00,
                    'rounding_precision' => 0,
                    'remarks_scale_json' => $do8Remarks,
                    'policy_reference_id' => $do15?->id,
                    'is_confirmed_by_admin' => false,
                    'is_active' => true,
                ]
            );
            $this->seedFlatComponentsIfEmpty($shsImmersion, [
                ['WW', 'Written / Oral Works', 20, 10],
                ['PT', 'Performance Tasks', 80, 20],
            ]);
        }

        // 6. Legacy SHS Presets (DO 8, s. 2015: 25/50/25 & 20/60/20)
        if ($legacyShs) {
            foreach ([
                ['shs_core', 'Legacy SHS Core / Academic Track (DO 8: 25/50/25)', 25, 50, 25],
                ['shs_techpro', 'Legacy SHS TVL / Sports / Arts Track (DO 8: 20/60/20)', 20, 60, 20],
            ] as [$wg, $name, $ww, $pt, $qa]) {
                $cfg = GradingConfiguration::query()->firstOrCreate(
                    ['curriculum_id' => $legacyShs->id, 'weight_group' => $wg, 'grade_level_id' => null, 'school_year_id' => null],
                    [
                        'name' => $name,
                        'grading_mode' => GradingConfiguration::MODE_NUMERICAL_TRANSMUTED,
                        'transmutation_table_id' => $do8Table->id,
                        'passing_grade' => 75.00,
                        'rounding_precision' => 0,
                        'remarks_scale_json' => $do8Remarks,
                        'policy_reference_id' => $do8?->id,
                        'is_confirmed_by_admin' => false,
                        'is_active' => true,
                    ]
                );
                $this->seedFlatComponentsIfEmpty($cfg, [
                    ['WW', 'Written Work', $ww, 10],
                    ['PT', 'Performance Tasks', $pt, 20],
                    ['QA', 'Quarterly Assessment', $qa, 30],
                ]);
            }
        }
    }

    private function seedRowsIfEmpty(TransmutationTable $table, array $rows): void
    {
        if ($table->rows()->exists()) {
            return;
        }

        $insert = array_map(fn (array $r) => [
            'transmutation_table_id' => $table->id,
            'min_initial_grade' => $r[0],
            'max_initial_grade' => $r[1],
            'transmuted_grade' => $r[2],
        ], $rows);

        TransmutationTableRow::query()->insert($insert);
    }

    private function seedFlatComponentsIfEmpty(GradingConfiguration $config, array $components): void
    {
        if ($config->components()->exists()) {
            return;
        }

        foreach ($components as [$code, $name, $weight, $sort]) {
            GradingComponent::query()->create([
                'grading_configuration_id' => $config->id,
                'code' => $code,
                'name' => $name,
                'weight_percentage' => $weight,
                'sort_order' => $sort,
            ]);
        }
    }

    private function seedDo15ComponentsIfEmpty(GradingConfiguration $config, float $ww, float $pt, float $ex): void
    {
        if ($config->components()->exists()) {
            return;
        }

        GradingComponent::query()->create([
            'grading_configuration_id' => $config->id,
            'code' => 'WW',
            'name' => 'Written / Oral Works',
            'weight_percentage' => $ww,
            'sort_order' => 10,
        ]);

        GradingComponent::query()->create([
            'grading_configuration_id' => $config->id,
            'code' => 'PT',
            'name' => 'Performance Tasks',
            'weight_percentage' => $pt,
            'sort_order' => 20,
        ]);

        $exam = GradingComponent::query()->create([
            'grading_configuration_id' => $config->id,
            'code' => 'EX',
            'name' => 'Examinations',
            'weight_percentage' => $ex,
            'sort_order' => 30,
        ]);

        foreach ([
            ['ST1', 'Summative Test 1', 30, 31],
            ['ST2', 'Summative Test 2', 30, 32],
            ['TE', 'Term Examination', 40, 33],
        ] as [$code, $name, $subWeight, $sort]) {
            GradingComponent::query()->create([
                'grading_configuration_id' => $config->id,
                'parent_component_id' => $exam->id,
                'code' => $code,
                'name' => $name,
                'weight_percentage' => $subWeight,
                'sort_order' => $sort,
            ]);
        }
    }
}
