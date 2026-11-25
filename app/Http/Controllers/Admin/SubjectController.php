<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\GradeLevel;
use App\Models\ShsTrack;
use App\Models\Subject;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        $curricula = Curriculum::query()->orderBy('id')->get();
        $gradeLevels = GradeLevel::query()->ordered()->get();
        $tracks = ShsTrack::query()->with('strands')->where('is_active', true)->orderBy('name')->get();

        $query = Subject::query()
            ->with(['curriculum', 'gradeLevel', 'shsTrack', 'shsStrand', 'parent'])
            ->orderBy('curriculum_id')
            ->orderBy('grade_level_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($curriculumId = $request->integer('curriculum_id')) {
            $query->where('curriculum_id', $curriculumId);
        }

        if ($gradeLevelId = $request->integer('grade_level_id')) {
            $query->where('grade_level_id', $gradeLevelId);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $subjects = $query->paginate(25)->withQueryString();

        return view('admin.subjects.index', [
            'subjects' => $subjects,
            'curricula' => $curricula,
            'gradeLevels' => $gradeLevels,
            'tracks' => $tracks,
            'parentCandidates' => Subject::query()->whereNull('parent_subject_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'curriculum_id' => ['required', 'exists:curricula,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'shs_track_id' => ['nullable', 'exists:shs_tracks,id'],
            'shs_strand_id' => ['nullable', 'exists:shs_strands,id'],
            'parent_subject_id' => ['nullable', 'exists:subjects,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('subjects', 'code')
                    ->where('curriculum_id', $request->input('curriculum_id'))
                    ->where('grade_level_id', $request->input('grade_level_id')),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'subject_category' => ['required', Rule::in(['learning_area', 'core', 'applied', 'specialized', 'elective', 'doorway'])],
            'weight_group' => ['required', 'string', 'max:50'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_graded' => ['nullable', 'boolean'],
        ]);

        $subject = Subject::query()->create([
            ...$validated,
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'sort_order' => $validated['sort_order'] ?? 50,
            'is_graded' => $request->boolean('is_graded', true),
            'is_active' => true,
        ]);

        $this->auditLog->record(
            eventType: 'subject.created',
            auditable: $subject,
            newValues: $subject->only(['curriculum_id', 'grade_level_id', 'code', 'name', 'weight_group']),
            request: $request
        );

        return redirect()
            ->route('admin.subjects.index', [
                'curriculum_id' => $subject->curriculum_id,
                'grade_level_id' => $subject->grade_level_id,
            ])
            ->with('status', "Subject {$subject->code} – {$subject->name} created.");
    }

    public function toggle(Request $request, Subject $subject): RedirectResponse
    {
        $oldState = $subject->is_active;
        $subject->update(['is_active' => ! $oldState]);

        $this->auditLog->record(
            eventType: 'subject.toggled',
            auditable: $subject,
            oldValues: ['is_active' => $oldState],
            newValues: ['is_active' => $subject->is_active],
            request: $request
        );

        return back()->with('status', "Subject {$subject->name} status updated.");
    }
}
