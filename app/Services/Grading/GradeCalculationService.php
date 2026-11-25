<?php

namespace App\Services\Grading;

use App\Models\GradingComponent;
use App\Models\GradingConfiguration;

class GradeCalculationService
{
    public function __construct(private readonly TransmutationService $transmutationService)
    {
    }

    /**
     * Compute a learner's period (quarterly / term) grade using the active
     * configurable GradingConfiguration.
     *
     * @param  array<int, array<int, array{raw_score: float|null, max_score: float, status: string}>>  $scoresByComponentId
     * @return array{
     *   initial_grade: float|null,
     *   transmuted_grade: float|null,
     *   final_grade: float|null,
     *   descriptive_rating: string|null,
     *   remarks: string|null,
     *   component_breakdown: array<int, array<string, mixed>>,
     *   missing_count: int,
     *   has_scores: bool
     * }
     */
    public function computePeriodGrade(
        GradingConfiguration $config,
        array $scoresByComponentId,
        ?string $descriptiveRating = null
    ): array {
        if ($config->isDescriptive()) {
            $scale = $config->descriptive_scale_json ?? [];
            $label = null;
            if ($descriptiveRating !== null) {
                foreach ($scale as $entry) {
                    if (($entry['code'] ?? null) === $descriptiveRating) {
                        $label = $entry['label'] ?? $descriptiveRating;
                        break;
                    }
                }
                $label ??= $descriptiveRating;
            }

            return [
                'initial_grade' => null,
                'transmuted_grade' => null,
                'final_grade' => null,
                'descriptive_rating' => $descriptiveRating,
                'remarks' => $label,
                'component_breakdown' => [],
                'missing_count' => $descriptiveRating === null ? 1 : 0,
                'has_scores' => $descriptiveRating !== null,
            ];
        }

        $topComponents = $config->relationLoaded('topLevelComponents')
            ? $config->topLevelComponents
            : $config->topLevelComponents()->with('children')->get();

        $breakdown = [];
        $initialGradeSum = 0.0;
        $totalMissing = 0;
        $anyHps = false;

        /** @var GradingComponent $component */
        foreach ($topComponents as $component) {
            if ($component->hasChildren()) {
                $childBreakdowns = [];
                $parentPs = 0.0;
                $parentHasHps = false;
                $parentMissing = 0;

                foreach ($component->children as $child) {
                    $leaf = $this->computeLeafMetrics($scoresByComponentId[$child->id] ?? []);
                    $childContribution = $leaf['ps'] !== null
                        ? round($leaf['ps'] * ($child->weight_percentage / 100), 4)
                        : 0.0;

                    if ($leaf['hps'] > 0) {
                        $parentHasHps = true;
                        $anyHps = true;
                    }

                    $parentPs += $childContribution;
                    $parentMissing += $leaf['missing_count'];

                    $childBreakdowns[] = [
                        'component_id' => $child->id,
                        'code' => $child->code,
                        'name' => $child->name,
                        'weight_percentage' => $child->weight_percentage,
                        'total_raw' => $leaf['total_raw'],
                        'hps' => $leaf['hps'],
                        'ps' => $leaf['ps'],
                        'weighted_sub_ps' => round($childContribution, 2),
                        'missing_count' => $leaf['missing_count'],
                    ];
                }

                $computedParentPs = $parentHasHps ? round($parentPs, 2) : null;
                $ws = $computedParentPs !== null
                    ? round($computedParentPs * ($component->weight_percentage / 100), 2)
                    : 0.0;

                $initialGradeSum += $ws;
                $totalMissing += $parentMissing;

                $breakdown[] = [
                    'component_id' => $component->id,
                    'code' => $component->code,
                    'name' => $component->name,
                    'weight_percentage' => $component->weight_percentage,
                    'ps' => $computedParentPs,
                    'ws' => $ws,
                    'missing_count' => $parentMissing,
                    'children' => $childBreakdowns,
                ];
            } else {
                $leaf = $this->computeLeafMetrics($scoresByComponentId[$component->id] ?? []);
                if ($leaf['hps'] > 0) {
                    $anyHps = true;
                }

                $ws = $leaf['ps'] !== null
                    ? round($leaf['ps'] * ($component->weight_percentage / 100), 2)
                    : 0.0;

                $initialGradeSum += $ws;
                $totalMissing += $leaf['missing_count'];

                $breakdown[] = [
                    'component_id' => $component->id,
                    'code' => $component->code,
                    'name' => $component->name,
                    'weight_percentage' => $component->weight_percentage,
                    'total_raw' => $leaf['total_raw'],
                    'hps' => $leaf['hps'],
                    'ps' => $leaf['ps'],
                    'ws' => $ws,
                    'missing_count' => $leaf['missing_count'],
                    'children' => [],
                ];
            }
        }

        if (! $anyHps) {
            return [
                'initial_grade' => null,
                'transmuted_grade' => null,
                'final_grade' => null,
                'descriptive_rating' => null,
                'remarks' => null,
                'component_breakdown' => $breakdown,
                'missing_count' => $totalMissing,
                'has_scores' => false,
            ];
        }

        $initialGrade = round(min(100.0, max(0.0, $initialGradeSum)), 2);
        $transmutedGrade = null;

        if ($config->usesTransmutation() && $config->transmutationTable) {
            $transmutedGrade = $this->transmutationService->transmute($config->transmutationTable, $initialGrade);
            $finalGrade = round($transmutedGrade, $config->rounding_precision);
        } else {
            // Zero-based direct grading (DO 015, s. 2026 SY 2027-2028+)
            $finalGrade = round($initialGrade, $config->rounding_precision);
        }

        $remarks = $this->resolveRemarks($config, $finalGrade);

        return [
            'initial_grade' => $initialGrade,
            'transmuted_grade' => $transmutedGrade,
            'final_grade' => $finalGrade,
            'descriptive_rating' => null,
            'remarks' => $remarks,
            'component_breakdown' => $breakdown,
            'missing_count' => $totalMissing,
            'has_scores' => true,
        ];
    }

    /**
     * @param  array<int, array{raw_score: float|null, max_score: float, status: string}>  $entries
     * @return array{total_raw: float, hps: float, ps: float|null, missing_count: int}
     */
    private function computeLeafMetrics(array $entries): array
    {
        $totalRaw = 0.0;
        $hps = 0.0;
        $missing = 0;

        foreach ($entries as $entry) {
            $status = $entry['status'] ?? 'scored';
            $maxScore = (float) ($entry['max_score'] ?? 0);

            if ($status === 'excused' || $maxScore <= 0) {
                continue;
            }

            $hps += $maxScore;

            if ($status === 'missing' || $entry['raw_score'] === null) {
                $missing++;
                continue;
            }

            $raw = (float) $entry['raw_score'];
            $totalRaw += min($maxScore, max(0.0, $raw));
        }

        $ps = $hps > 0 ? round(($totalRaw / $hps) * 100, 2) : null;

        return [
            'total_raw' => round($totalRaw, 2),
            'hps' => round($hps, 2),
            'ps' => $ps,
            'missing_count' => $missing,
        ];
    }

    public function resolveRemarks(GradingConfiguration $config, float $grade): string
    {
        $scale = $config->remarks_scale_json ?? [];
        foreach ($scale as $band) {
            $min = isset($band['min']) ? (float) $band['min'] : 0.0;
            $max = isset($band['max']) ? (float) $band['max'] : 100.0;
            if ($grade >= $min && $grade <= $max && ! empty($band['label'])) {
                return (string) $band['label'];
            }
        }

        return $grade >= $config->passing_grade ? 'Passed' : 'Did Not Meet Expectations';
    }

    /**
     * Compute a subject's Final Grade across all grading periods in a school year.
     *
     * @param  array<int, float|int|null>  $periodGrades
     */
    public function computeFinalSubjectGrade(array $periodGrades, int $precision = 0): ?float
    {
        $valid = array_values(array_filter($periodGrades, fn ($g) => $g !== null));
        if (empty($valid)) {
            return null;
        }

        return round(array_sum($valid) / count($valid), $precision);
    }

    /**
     * Compute General Average and Promotion/Retention status across graded subjects.
     *
     * @param  array<int, float|int|null>  $subjectFinalGrades
     * @return array{general_average: float|null, failed_count: int, promotion_status: string}
     */
    public function computeGeneralAverageAndStatus(
        array $subjectFinalGrades,
        float $passingGrade = 75.0,
        int $maxRemediableFailures = 2,
        int $precision = 0
    ): array {
        if (empty($subjectFinalGrades)) {
            return [
                'general_average' => null,
                'failed_count' => 0,
                'promotion_status' => 'incomplete',
            ];
        }

        foreach ($subjectFinalGrades as $grade) {
            if ($grade === null) {
                return [
                    'general_average' => null,
                    'failed_count' => 0,
                    'promotion_status' => 'incomplete',
                ];
            }
        }

        $avg = round(array_sum($subjectFinalGrades) / count($subjectFinalGrades), $precision);
        $failed = count(array_filter($subjectFinalGrades, fn ($g) => (float) $g < $passingGrade));

        $status = match (true) {
            $failed === 0 => 'promoted',
            $failed <= $maxRemediableFailures => 'conditional',
            default => 'retained',
        };

        return [
            'general_average' => $avg,
            'failed_count' => $failed,
            'promotion_status' => $status,
        ];
    }
}
