<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\StudentIntervention;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterventionController extends Controller
{
    public function index(Request $request): View
    {
        $activeSy = SchoolYear::current();
        $sections = Section::query()
            ->with('gradeLevel')
            ->when($activeSy, fn ($q) => $q->where('school_year_id', $activeSy->id))
            ->where('is_active', true)
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();

        $query = StudentIntervention::query()
            ->with(['enrollment.student', 'enrollment.section.gradeLevel', 'enrollment.schoolYear', 'subject', 'recorder'])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('follow_up_date')
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($sectionId = $request->integer('section_id')) {
            $query->whereHas('enrollment', fn ($q) => $q->where('section_id', $sectionId));
        }

        $interventions = $query->paginate(20)->withQueryString();

        $counts = [
            'open' => StudentIntervention::query()->where('status', StudentIntervention::STATUS_OPEN)->count(),
            'overdue' => StudentIntervention::query()
                ->where('status', StudentIntervention::STATUS_OPEN)
                ->whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '<', today())
                ->count(),
            'resolved' => StudentIntervention::query()->where('status', StudentIntervention::STATUS_RESOLVED)->count(),
        ];

        return view('admin.interventions.index', [
            'interventions' => $interventions,
            'sections' => $sections,
            'categories' => StudentIntervention::categoryLabels(),
            'counts' => $counts,
        ]);
    }
}
