<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\GradeLevel;
use App\Models\PolicyReference;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class DepEdCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Official DepEd Policy Reference Registry (flagged unconfirmed until school admin confirms)
        $policies = [
            'DO-010-2024' => PolicyReference::query()->updateOrCreate(
                ['document_number' => 'DO 010, s. 2024'],
                [
                    'title' => 'Policy Guidelines on the Implementation of the MATATAG Curriculum',
                    'publication_date' => '2024-07-23',
                    'applicable_grade_levels' => 'Kindergarten to Grade 10 (Phased: K/G1/G4/G7 SY 2024-25; G2/G5/G8 SY 2025-26; G3/G6/G9 SY 2026-27; G10 SY 2027-28)',
                    'implementation_school_year' => 'SY 2024-2025 to SY 2027-2028',
                    'source_url' => 'https://www.deped.gov.ph/',
                    'is_verified' => true,
                    'notes' => 'Decongests K-10 curriculum; streamlines early grade learning areas (Language, Reading & Literacy, Makabansa, Mathematics, GMRC). Confirm applicability per grade level for your school.',
                ]
            ),
            'DO-015-2026' => PolicyReference::query()->updateOrCreate(
                ['document_number' => 'DO 015, s. 2026'],
                [
                    'title' => 'Revised Guidelines on Classroom Assessment, Grading System, and Awards and Recognition for the K to 12 Basic Education Program',
                    'publication_date' => '2026-06-04',
                    'applicable_grade_levels' => 'Kindergarten to Grade 12 (Key Stages 1 to 4)',
                    'implementation_school_year' => 'SY 2026-2027 onward (Transition SY 2026-27; Full Zero-Based SY 2027-28)',
                    'source_url' => 'https://www.deped.gov.ph/',
                    'is_verified' => true,
                    'notes' => 'Repeals DO 8, s. 2015 and DO 36, s. 2016. Introduces Key Stage 1 descriptive grading, 3-term alignment, updated component weights (with ST1/ST2/TE exam sub-weights), and transition to zero-based grading. Confirm exact Annex tables before locking school policy.',
                ]
            ),
            'DO-017-2026' => PolicyReference::query()->updateOrCreate(
                ['document_number' => 'DO 017, s. 2026'],
                [
                    'title' => 'Strengthened Senior High School Curriculum',
                    'publication_date' => '2026-06-04',
                    'applicable_grade_levels' => 'Grade 11 and Grade 12 (Key Stage 4)',
                    'implementation_school_year' => 'SY 2026-2027 (Full Grade 11 rollout)',
                    'source_url' => 'https://www.deped.gov.ph/',
                    'is_verified' => true,
                    'notes' => 'Streamlines SHS into Academic and Technical-Professional (TechPro) tracks with reduced core subjects, elective clusters, and doorway options.',
                ]
            ),
            'DM-012-2026' => PolicyReference::query()->updateOrCreate(
                ['document_number' => 'DM 012, s. 2026'],
                [
                    'title' => 'Full Implementation of the Strengthened Senior High School Curriculum in School Year 2026-2027',
                    'publication_date' => '2026-02-27',
                    'applicable_grade_levels' => 'Incoming Grade 11 Learners (SY 2026-2027)',
                    'implementation_school_year' => 'SY 2026-2027',
                    'source_url' => 'https://www.deped.gov.ph/',
                    'is_verified' => true,
                    'notes' => 'Directs nationwide rollout of Strengthened SHS for incoming Grade 11 while Grade 12 cohorts complete the existing curriculum.',
                ]
            ),
            'DO-008-2015' => PolicyReference::query()->updateOrCreate(
                ['document_number' => 'DO 8, s. 2015'],
                [
                    'title' => 'Policy Guidelines on Classroom Assessment for the K to 12 Basic Education Program',
                    'publication_date' => '2015-04-01',
                    'applicable_grade_levels' => 'Grades 1 to 12 (Historical / Legacy K-12)',
                    'implementation_school_year' => 'SY 2015-2016 to SY 2025-2026',
                    'source_url' => 'https://www.deped.gov.ph/',
                    'is_verified' => true,
                    'notes' => 'Retained as a configurable preset for historical academic records and schools still operating on the 4-quarter WW/PT/QA + Appendix B Transmutation framework.',
                ]
            ),
            'DO-021-2019' => PolicyReference::query()->updateOrCreate(
                ['document_number' => 'DO 21, s. 2019'],
                [
                    'title' => 'Policy Guidelines on the K to 12 Basic Education Program',
                    'publication_date' => '2019-08-22',
                    'applicable_grade_levels' => 'Kindergarten to Grade 12 (Legacy K-12 & Legacy SHS Strands)',
                    'implementation_school_year' => 'SY 2019-2020 onward (Amended by DO 010, s. 2024 & DO 017, s. 2026)',
                    'source_url' => 'https://www.deped.gov.ph/',
                    'is_verified' => true,
                    'notes' => 'Retained for legacy grade levels during phased curriculum transition and historical Senior High School strands (STEM, ABM, HUMSS, GAS, TVL, Sports, Arts & Design).',
                ]
            ),
        ];

        // 2. Philippine Basic Education Grade Levels (Kindergarten to Grade 12)
        $gradeLevelDefs = [
            ['code' => 'K',   'name' => 'Kindergarten', 'key_stage' => 'KS1', 'education_level' => 'kindergarten', 'sort_order' => 0],
            ['code' => 'G1',  'name' => 'Grade 1',      'key_stage' => 'KS1', 'education_level' => 'elementary',   'sort_order' => 1],
            ['code' => 'G2',  'name' => 'Grade 2',      'key_stage' => 'KS1', 'education_level' => 'elementary',   'sort_order' => 2],
            ['code' => 'G3',  'name' => 'Grade 3',      'key_stage' => 'KS1', 'education_level' => 'elementary',   'sort_order' => 3],
            ['code' => 'G4',  'name' => 'Grade 4',      'key_stage' => 'KS2', 'education_level' => 'elementary',   'sort_order' => 4],
            ['code' => 'G5',  'name' => 'Grade 5',      'key_stage' => 'KS2', 'education_level' => 'elementary',   'sort_order' => 5],
            ['code' => 'G6',  'name' => 'Grade 6',      'key_stage' => 'KS2', 'education_level' => 'elementary',   'sort_order' => 6],
            ['code' => 'G7',  'name' => 'Grade 7',      'key_stage' => 'KS3', 'education_level' => 'junior_high',  'sort_order' => 7],
            ['code' => 'G8',  'name' => 'Grade 8',      'key_stage' => 'KS3', 'education_level' => 'junior_high',  'sort_order' => 8],
            ['code' => 'G9',  'name' => 'Grade 9',      'key_stage' => 'KS3', 'education_level' => 'junior_high',  'sort_order' => 9],
            ['code' => 'G10', 'name' => 'Grade 10',     'key_stage' => 'KS3', 'education_level' => 'junior_high',  'sort_order' => 10],
            ['code' => 'G11', 'name' => 'Grade 11',     'key_stage' => 'KS4', 'education_level' => 'senior_high',  'sort_order' => 11],
            ['code' => 'G12', 'name' => 'Grade 12',     'key_stage' => 'KS4', 'education_level' => 'senior_high',  'sort_order' => 12],
        ];

        $grades = [];
        foreach ($gradeLevelDefs as $def) {
            $grades[$def['code']] = GradeLevel::query()->updateOrCreate(
                ['code' => $def['code']],
                $def
            );
        }

        // 3. Configurable Curriculum Versions (MATATAG, Legacy K-12, Strengthened SHS, Legacy SHS)
        $matatagK10 = Curriculum::query()->updateOrCreate(
            ['code' => 'MATATAG-K10-2024'],
            [
                'name' => 'MATATAG Curriculum (K to 10)',
                'version' => '2024.1',
                'education_stage' => 'k_to_10',
                'policy_reference_id' => $policies['DO-010-2024']->id,
                'status' => 'active',
                'description' => 'Official DepEd MATATAG K-10 decongested curriculum under DO 010, s. 2024. Assignable per grade level and school year for phased transition.',
            ]
        );

        $legacyK12 = Curriculum::query()->updateOrCreate(
            ['code' => 'K12-LEGACY-2015'],
            [
                'name' => 'K to 12 Basic Education Curriculum (Legacy K–10)',
                'version' => '2019.1',
                'education_stage' => 'k_to_10',
                'policy_reference_id' => $policies['DO-021-2019']->id,
                'status' => 'transitioning',
                'description' => 'Previous K-10 curriculum retained for grade levels not yet phased into MATATAG and for accurate historical report card generation.',
            ]
        );

        $sshs = Curriculum::query()->updateOrCreate(
            ['code' => 'SSHS-2026'],
            [
                'name' => 'Strengthened Senior High School Curriculum',
                'version' => '2026.1',
                'education_stage' => 'senior_high',
                'policy_reference_id' => $policies['DO-017-2026']->id,
                'status' => 'active',
                'description' => 'DepEd Strengthened Senior High School Curriculum (DO 017, s. 2026 & DM 012, s. 2026) with Academic and TechPro tracks, streamlined core subjects, elective clusters, and doorway options.',
            ]
        );

        $legacyShs = Curriculum::query()->updateOrCreate(
            ['code' => 'SHS-LEGACY-2019'],
            [
                'name' => 'Legacy Senior High School Curriculum (DO 21, s. 2019)',
                'version' => '2019.1',
                'education_stage' => 'senior_high',
                'policy_reference_id' => $policies['DO-021-2019']->id,
                'status' => 'transitioning',
                'description' => 'Previous SHS track/strand structure (Academic: STEM, ABM, HUMSS, GAS; TVL; Sports; Arts & Design) preserved for Grade 12 transitioning cohorts and historical transcripts.',
            ]
        );

        // 4. SHS Tracks & Strands / Elective Clusters
        $sshsAcademic = ShsTrack::query()->updateOrCreate(
            ['curriculum_id' => $sshs->id, 'code' => 'ACAD'],
            ['name' => 'Academic Track', 'is_active' => true]
        );
        $sshsTechPro = ShsTrack::query()->updateOrCreate(
            ['curriculum_id' => $sshs->id, 'code' => 'TECHPRO'],
            ['name' => 'Technical-Professional (TechPro) Track', 'is_active' => true]
        );

        foreach ([
            [$sshsAcademic, 'ASH', 'Arts, Social Sciences & Humanities Cluster'],
            [$sshsAcademic, 'BE', 'Business & Entrepreneurship Cluster'],
            [$sshsAcademic, 'STEM-C', 'Science, Technology, Engineering & Mathematics Cluster'],
            [$sshsAcademic, 'SHW', 'Sports, Health & Wellness Cluster'],
            [$sshsTechPro, 'ICT', 'Information & Communications Technology Cluster'],
            [$sshsTechPro, 'IA', 'Industrial Arts & Engineering Services Cluster'],
            [$sshsTechPro, 'HE', 'Hospitality, Tourism & Home Economics Cluster'],
            [$sshsTechPro, 'AFA', 'Agri-Fishery Arts Cluster'],
        ] as [$track, $code, $name]) {
            ShsStrand::query()->updateOrCreate(
                ['shs_track_id' => $track->id, 'code' => $code],
                ['name' => $name, 'type' => 'elective_cluster', 'is_active' => true]
            );
        }

        $legacyAcademic = ShsTrack::query()->updateOrCreate(
            ['curriculum_id' => $legacyShs->id, 'code' => 'ACAD'],
            ['name' => 'Academic Track', 'is_active' => true]
        );
        $legacyTvl = ShsTrack::query()->updateOrCreate(
            ['curriculum_id' => $legacyShs->id, 'code' => 'TVL'],
            ['name' => 'Technical-Vocational-Livelihood (TVL) Track', 'is_active' => true]
        );
        ShsTrack::query()->updateOrCreate(
            ['curriculum_id' => $legacyShs->id, 'code' => 'SPORTS'],
            ['name' => 'Sports Track', 'is_active' => true]
        );
        ShsTrack::query()->updateOrCreate(
            ['curriculum_id' => $legacyShs->id, 'code' => 'ARTS'],
            ['name' => 'Arts and Design Track', 'is_active' => true]
        );

        foreach ([
            [$legacyAcademic, 'STEM', 'Science, Technology, Engineering, and Mathematics'],
            [$legacyAcademic, 'ABM', 'Accountancy, Business, and Management'],
            [$legacyAcademic, 'HUMSS', 'Humanities and Social Sciences'],
            [$legacyAcademic, 'GAS', 'General Academic Strand'],
            [$legacyTvl, 'TVL-ICT', 'Information and Communications Technology'],
            [$legacyTvl, 'TVL-HE', 'Home Economics'],
            [$legacyTvl, 'TVL-IA', 'Industrial Arts'],
            [$legacyTvl, 'TVL-AFA', 'Agri-Fishery Arts'],
        ] as [$track, $code, $name]) {
            ShsStrand::query()->updateOrCreate(
                ['shs_track_id' => $track->id, 'code' => $code],
                ['name' => $name, 'type' => 'strand', 'is_active' => true]
            );
        }

        // 5. Configurable MATATAG K-10 Learning Area Templates (editable per school)
        $this->seedSubjectList($matatagK10->id, $grades['K']->id, [
            ['KIND-DEV', 'Kindergarten Developmental Domains', 'descriptive_ks1', 10],
        ]);

        // Grade 1 MATATAG: Streamlined 5 Core Learning Areas (DO 010, s. 2024)
        $this->seedSubjectList($matatagK10->id, $grades['G1']->id, [
            ['LANG1', 'Language', 'languages_ap_gmrc', 10],
            ['READ1', 'Reading and Literacy', 'languages_ap_gmrc', 20],
            ['MATH1', 'Mathematics', 'math_science', 30],
            ['MAKA1', 'Makabansa', 'languages_ap_gmrc', 40],
            ['GMRC1', 'Good Manners and Right Conduct (GMRC)', 'languages_ap_gmrc', 50],
        ]);

        // Grades 2 & 3 MATATAG
        foreach (['G2', 'G3'] as $idx => $gCode) {
            $num = $idx + 2;
            $list = [
                ["FIL{$num}", 'Filipino', 'languages_ap_gmrc', 10],
                ["ENG{$num}", 'English', 'languages_ap_gmrc', 20],
                ["MATH{$num}", 'Mathematics', 'math_science', 30],
                ["MAKA{$num}", 'Makabansa', 'languages_ap_gmrc', 40],
                ["GMRC{$num}", 'Good Manners and Right Conduct (GMRC)', 'languages_ap_gmrc', 50],
            ];
            if ($num === 3) {
                $list[] = ['SCI3', 'Science', 'math_science', 35];
            }
            $this->seedSubjectList($matatagK10->id, $grades[$gCode]->id, $list);
        }

        // Grades 4 to 6 MATATAG (Key Stage 2)
        foreach (['G4', 'G5', 'G6'] as $idx => $gCode) {
            $num = $idx + 4;
            $this->seedSubjectList($matatagK10->id, $grades[$gCode]->id, [
                ["FIL{$num}", 'Filipino', 'languages_ap_gmrc', 10],
                ["ENG{$num}", 'English', 'languages_ap_gmrc', 20],
                ["MATH{$num}", 'Mathematics', 'math_science', 30],
                ["SCI{$num}", 'Science', 'math_science', 40],
                ["AP{$num}", 'Araling Panlipunan', 'languages_ap_gmrc', 50],
                ["EPP{$num}", 'Edukasyong Pantahanan at Pangkabuhayan (EPP)', 'mapeh_epp_tle', 60],
                ["GMRC{$num}", 'Good Manners and Right Conduct (GMRC)', 'languages_ap_gmrc', 70],
            ]);
            $this->seedMapehWithSubComponents($matatagK10->id, $grades[$gCode]->id, $num);
        }

        // Grades 7 to 10 MATATAG (Key Stage 3 - Junior High School)
        foreach (['G7', 'G8', 'G9', 'G10'] as $idx => $gCode) {
            $num = $idx + 7;
            $this->seedSubjectList($matatagK10->id, $grades[$gCode]->id, [
                ["FIL{$num}", 'Filipino', 'languages_ap_gmrc', 10],
                ["ENG{$num}", 'English', 'languages_ap_gmrc', 20],
                ["MATH{$num}", 'Mathematics', 'math_science', 30],
                ["SCI{$num}", 'Science', 'math_science', 40],
                ["AP{$num}", 'Araling Panlipunan', 'languages_ap_gmrc', 50],
                ["TLE{$num}", 'Technology and Livelihood Education (TLE)', 'mapeh_epp_tle', 60],
                ["VE{$num}", 'Values Education', 'languages_ap_gmrc', 70],
            ]);
            $this->seedMapehWithSubComponents($matatagK10->id, $grades[$gCode]->id, $num);
        }

        // Legacy K-12 Grade 10 template (for schools transitioning Grade 10 in SY 2026-2027)
        $this->seedSubjectList($legacyK12->id, $grades['G10']->id, [
            ['FIL10-K12', 'Filipino', 'languages_ap_gmrc', 10],
            ['ENG10-K12', 'English', 'languages_ap_gmrc', 20],
            ['MATH10-K12', 'Mathematics', 'math_science', 30],
            ['SCI10-K12', 'Science', 'math_science', 40],
            ['AP10-K12', 'Araling Panlipunan', 'languages_ap_gmrc', 50],
            ['ESP10-K12', 'Edukasyon sa Pagpapakatao (EsP)', 'languages_ap_gmrc', 60],
            ['TLE10-K12', 'Technology and Livelihood Education (TLE)', 'mapeh_epp_tle', 70],
            ['MAPEH10-K12', 'MAPEH', 'mapeh_epp_tle', 80],
        ]);

        // Strengthened SHS Grade 11 Streamlined Core Subjects (DO 017, s. 2026 — configurable by admin)
        $this->seedSubjectList($sshs->id, $grades['G11']->id, [
            ['SSHS-COMM', 'Effective Communication', 'shs_core', 10, 'core'],
            ['SSHS-MATH', 'General Mathematics', 'shs_core', 20, 'core'],
            ['SSHS-SCI', 'General Science', 'shs_core', 30, 'core'],
            ['SSHS-LIFE', 'Life and Career Skills', 'shs_core', 40, 'core'],
            ['SSHS-HIST', 'Pag-aaral ng Kasaysayan at Lipunang Pilipino', 'shs_core', 50, 'core'],
        ]);
    }

    private function seedSubjectList(int $curriculumId, int $gradeLevelId, array $items): void
    {
        foreach ($items as $item) {
            [$code, $name, $weightGroup, $sortOrder] = $item;
            $category = $item[4] ?? 'learning_area';

            Subject::query()->updateOrCreate(
                [
                    'curriculum_id' => $curriculumId,
                    'grade_level_id' => $gradeLevelId,
                    'code' => $code,
                ],
                [
                    'name' => $name,
                    'subject_category' => $category,
                    'weight_group' => $weightGroup,
                    'sort_order' => $sortOrder,
                    'is_graded' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedMapehWithSubComponents(int $curriculumId, int $gradeLevelId, int $gradeNum): void
    {
        $mapeh = Subject::query()->updateOrCreate(
            [
                'curriculum_id' => $curriculumId,
                'grade_level_id' => $gradeLevelId,
                'code' => "MAPEH{$gradeNum}",
            ],
            [
                'name' => 'MAPEH (Music, Arts, PE & Health)',
                'subject_category' => 'learning_area',
                'weight_group' => 'mapeh_epp_tle',
                'sort_order' => 80,
                'is_graded' => true,
                'is_active' => true,
            ]
        );

        $subComponents = [
            ["MUS{$gradeNum}", 'Music', 81],
            ["ART{$gradeNum}", 'Arts', 82],
            ["PE{$gradeNum}", 'Physical Education', 83],
            ["HLTH{$gradeNum}", 'Health', 84],
        ];

        foreach ($subComponents as [$code, $name, $sort]) {
            Subject::query()->updateOrCreate(
                [
                    'curriculum_id' => $curriculumId,
                    'grade_level_id' => $gradeLevelId,
                    'code' => $code,
                ],
                [
                    'parent_subject_id' => $mapeh->id,
                    'name' => $name,
                    'subject_category' => 'learning_area',
                    'weight_group' => 'mapeh_epp_tle',
                    'sort_order' => $sort,
                    'is_graded' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
