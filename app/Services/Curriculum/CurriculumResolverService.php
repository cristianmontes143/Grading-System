<?php

namespace App\Services\Curriculum;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CurriculumResolverService
{
    public function resolveFor(int $schoolYearId, int $gradeLevelId): ?Curriculum
    {
        $implementation = CurriculumGradeImplementation::query()
            ->with('curriculum.policyReference')
            ->where('school_year_id', $schoolYearId)
            ->where('grade_level_id', $gradeLevelId)
            ->first();

        return $implementation?->curriculum;
    }

    /**
     * Resolve active subject offerings for a section strictly scoped to the
     * curriculum version assigned to that section's (school_year, grade_level).
     */
    public function getOfferingsForSection(Section $section, ?int $semester = null): Collection
    {
        $curriculum = $this->resolveFor($section->school_year_id, $section->grade_level_id);

        if (! $curriculum) {
            return collect();
        }

        $query = Subject::query()
            ->where('curriculum_id', $curriculum->id)
            ->where('grade_level_id', $section->grade_level_id)
            ->where('is_active', true)
            ->whereNull('parent_subject_id')
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($semester !== null) {
            $query->where(function (Builder $q) use ($semester) {
                $q->whereNull('semester')->orWhere('semester', $semester);
            });
        }

        if ($section->shs_track_id) {
            $query->where(function (Builder $q) use ($section) {
                // Core/general subjects for all tracks
                $q->whereNull('shs_track_id')
                    // Or subjects matching this section's track (and strand/cluster if specified)
                    ->orWhere(function (Builder $trackQ) use ($section) {
                        $trackQ->where('shs_track_id', $section->shs_track_id);
                        if ($section->shs_strand_id) {
                            $trackQ->where(function (Builder $strandQ) use ($section) {
                                $strandQ->whereNull('shs_strand_id')
                                    ->orWhere('shs_strand_id', $section->shs_strand_id);
                            });
                        }
                    });
            });
        }

        return $query->get();
    }

    public function activateSchoolYear(SchoolYear $schoolYear): void
    {
        DB::transaction(function () use ($schoolYear): void {
            SchoolYear::query()
                ->where('is_active', true)
                ->where('id', '!=', $schoolYear->id)
                ->update(['is_active' => false]);

            $schoolYear->update(['is_active' => true]);
        });
    }

    public function seedStandardPeriodsForSchoolYear(SchoolYear $schoolYear): void
    {
        $definitions = match ($schoolYear->term_structure) {
            SchoolYear::TERM_TRIMESTER => [
                ['code' => 'T1', 'name' => 'First Term', 'sequence' => 1, 'semester' => null],
                ['code' => 'T2', 'name' => 'Second Term', 'sequence' => 2, 'semester' => null],
                ['code' => 'T3', 'name' => 'Third Term', 'sequence' => 3, 'semester' => null],
            ],
            SchoolYear::TERM_SEMESTRAL => [
                ['code' => 'S1Q1', 'name' => '1st Semester – 1st Quarter', 'sequence' => 1, 'semester' => 1],
                ['code' => 'S1Q2', 'name' => '1st Semester – 2nd Quarter', 'sequence' => 2, 'semester' => 1],
                ['code' => 'S2Q1', 'name' => '2nd Semester – 3rd Quarter', 'sequence' => 3, 'semester' => 2],
                ['code' => 'S2Q2', 'name' => '2nd Semester – 4th Quarter', 'sequence' => 4, 'semester' => 2],
            ],
            default => [
                ['code' => 'Q1', 'name' => 'First Quarter', 'sequence' => 1, 'semester' => null],
                ['code' => 'Q2', 'name' => 'Second Quarter', 'sequence' => 2, 'semester' => null],
                ['code' => 'Q3', 'name' => 'Third Quarter', 'sequence' => 3, 'semester' => null],
                ['code' => 'Q4', 'name' => 'Fourth Quarter', 'sequence' => 4, 'semester' => null],
            ],
        };

        foreach ($definitions as $def) {
            GradingPeriod::query()->firstOrCreate(
                [
                    'school_year_id' => $schoolYear->id,
                    'code' => $def['code'],
                ],
                [
                    'name' => $def['name'],
                    'sequence' => $def['sequence'],
                    'semester' => $def['semester'],
                    'status' => GradingPeriod::STATUS_UPCOMING,
                ]
            );
        }
    }
}
