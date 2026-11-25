<?php

namespace App\Http\Controllers;

use App\Models\GradingConfiguration;
use App\Services\Grading\GradeCalculationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeSimulatorController extends Controller
{
    public function __construct(private readonly GradeCalculationService $calculator)
    {
    }

    public function index(Request $request): View
    {
        $configs = GradingConfiguration::query()
            ->with(['topLevelComponents.children', 'transmutationTable', 'curriculum', 'gradeLevel'])
            ->where('is_active', true)
            ->where('grading_mode', '!=', GradingConfiguration::MODE_DESCRIPTIVE)
            ->orderBy('name')
            ->get();

        $selectedConfig = $configs->firstWhere('id', $request->integer('config_id'))
            ?? $configs->first();

        $result = null;
        $submittedInputs = [];

        if ($selectedConfig && $request->isMethod('post')) {
            $rawScores = $request->input('raw', []);
            $maxScores = $request->input('max', []);
            $scoresByComponentId = [];

            foreach ($selectedConfig->topLevelComponents as $comp) {
                $leaves = $comp->hasChildren() ? $comp->children : collect([$comp]);
                foreach ($leaves as $leaf) {
                    $raw = $rawScores[$leaf->id] ?? null;
                    $max = $maxScores[$leaf->id] ?? null;
                    $submittedInputs[$leaf->id] = ['raw' => $raw, 'max' => $max];

                    if ($max !== null && $max !== '' && is_numeric($max) && (float) $max > 0) {
                        $isBlank = $raw === null || $raw === '';
                        $scoresByComponentId[$leaf->id][] = [
                            'raw_score' => $isBlank ? null : max(0.0, min((float) $max, (float) $raw)),
                            'max_score' => (float) $max,
                            'status' => $isBlank ? 'missing' : 'scored',
                        ];
                    }
                }
            }

            $result = $this->calculator->computePeriodGrade($selectedConfig, $scoresByComponentId);
        }

        return view('tools.grade-simulator', [
            'configs' => $configs,
            'selectedConfig' => $selectedConfig,
            'submittedInputs' => $submittedInputs,
            'result' => $result,
        ]);
    }
}
