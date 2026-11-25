<?php

namespace App\Services\Grading;

use App\Models\TransmutationTable;
use App\Models\TransmutationTableRow;
use RuntimeException;

class TransmutationService
{
    public function transmute(TransmutationTable $table, float $initialGrade): float
    {
        $ig = round(max(0.0, min(100.0, $initialGrade)), 2);

        $rows = $table->relationLoaded('rows')
            ? $table->rows
            : $table->rows()->orderByDesc('min_initial_grade')->get();

        if ($rows->isEmpty()) {
            throw new RuntimeException("Transmutation table [{$table->name}] has no rows configured.");
        }

        /** @var TransmutationTableRow|null $match */
        $match = $rows->first(function (TransmutationTableRow $row) use ($ig): bool {
            return $ig >= round($row->min_initial_grade, 2) && $ig <= round($row->max_initial_grade, 2);
        });

        if ($match) {
            return (float) $match->transmuted_grade;
        }

        // Graceful fallback for custom tables with boundary gaps: pick highest bracket <= IG
        $fallback = $rows
            ->filter(fn (TransmutationTableRow $row) => round($row->min_initial_grade, 2) <= $ig)
            ->sortByDesc('min_initial_grade')
            ->first();

        return (float) ($fallback?->transmuted_grade ?? $rows->last()->transmuted_grade);
    }
}
