<?php

namespace App\Services\Grading;

use App\Models\GradingConfiguration;
use App\Models\Subject;

class GradingConfigurationResolver
{
    /**
     * Resolve the most specific active GradingConfiguration for a given
     * (school_year, curriculum, grade_level, subject).
     * Never mixes configurations across different curricula.
     */
    public function resolve(
        int $schoolYearId,
        int $curriculumId,
        int $gradeLevelId,
        Subject $subject
    ): ?GradingConfiguration {
        $candidates = GradingConfiguration::query()
            ->with(['topLevelComponents.children', 'transmutationTable.rows', 'policyReference'])
            ->where('curriculum_id', $curriculumId)
            ->where('is_active', true)
            ->where(function ($q) use ($schoolYearId) {
                $q->where('school_year_id', $schoolYearId)
                    ->orWhereNull('school_year_id');
            })
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($candidates as $config) {
            // Reject incompatible scopes
            if ($config->subject_id !== null && (int) $config->subject_id !== (int) $subject->id) {
                continue;
            }
            if ($config->grade_level_id !== null && (int) $config->grade_level_id !== $gradeLevelId) {
                continue;
            }
            if ($config->weight_group !== null && $config->weight_group !== $subject->weight_group) {
                continue;
            }

            $score = 0;
            if ((int) $config->school_year_id === $schoolYearId) {
                $score += 100;
            }
            if ((int) $config->subject_id === (int) $subject->id) {
                $score += 50;
            }
            if ((int) $config->grade_level_id === $gradeLevelId) {
                $score += 20;
            }
            if ($config->weight_group !== null && $config->weight_group === $subject->weight_group) {
                $score += 10;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $config;
            }
        }

        return $best;
    }
}
