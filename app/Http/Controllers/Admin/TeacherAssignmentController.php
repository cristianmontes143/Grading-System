<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Services\Academic\TeacherAssignmentService;
use App\Services\Curriculum\CurriculumResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherAssignmentController extends Controller
{
    public function __construct(
        private readonly TeacherAssignmentService $assignmentService,
        private readonly CurriculumResolverService $curriculumResolver
    ) {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->orderByDesc('start_date')->get();
        $selectedSyId = $request->integer('school_year_id')
            ?: ($schoolYears->firstWhere('is_active', true)?->id ?? $schoolYears->first()?->id);

        $gradeLevels = GradeLevel::query()->active()->ordered()->get();
        $teachers = Teacher::query()->active()->orderBy('last_name')->orderBy('first_name')->get();

        $sectionsQuery = Section::query()
            ->with(['gradeLevel', 'shsTrack', 'shsStrand', 'adviser', 'activeTeacherAssignments.teacher', 'activeTeacherAssignments.subject'])
            ->where('is_active', true)
            ->orderBy('grade_level_id')
            ->orderBy('name');

        if ($selectedSyId) {
            $sectionsQuery->where('school_year_id', $selectedSyId);
        }

        if ($gradeLevelId = $request->integer('grade_level_id')) {
            $sectionsQuery->where('grade_level_id', $gradeLevelId);
        }

        $sections = $sectionsQuery->get();

        // Pre-resolve curriculum & eligible subjects per section
        $sectionMatrices = $sections->map(function (Section $section) {
            $curriculum = $this->curriculumResolver->resolveFor($section->school_year_id, $section->grade_level_id);
            $offerings = $this->curriculumResolver->getOfferingsForSection($section);
            $assignmentsBySubject = $section->activeTeacherAssignments->keyBy('subject_id');

            return [
                'section' => $section,
                'curriculum' => $curriculum,
                'offerings' => $offerings,
                'assignmentsBySubject' => $assignmentsBySubject,
            ];
        });

        return view('admin.assignments.index', [
            'schoolYears' => $schoolYears,
            'selectedSyId' => $selectedSyId,
            'gradeLevels' => $gradeLevels,
            'teachers' => $teachers,
            'sectionMatrices' => $sectionMatrices,
        ]);
    }

    public function assignAdviser(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'adviser_id' => ['nullable', 'exists:teachers,id'],
        ]);

        $teacher = ! empty($validated['adviser_id'])
            ? Teacher::query()->findOrFail($validated['adviser_id'])
            : null;

        $this->assignmentService->assignAdviser($section, $teacher);

        $msg = $teacher
            ? "{$teacher->full_name} assigned as Adviser of {$section->gradeLevel->name} – {$section->name}."
            : "Adviser unassigned from {$section->gradeLevel->name} – {$section->name}.";

        return redirect()
            ->route('admin.assignments.index', ['school_year_id' => $section->school_year_id])
            ->with('status', $msg);
    }

    public function assignSubject(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'schedule_summary' => ['nullable', 'string', 'max:150'],
        ]);

        $subject = Subject::query()->findOrFail($validated['subject_id']);
        $teacher = Teacher::query()->findOrFail($validated['teacher_id']);

        $this->assignmentService->assignSubjectTeacher(
            $section,
            $subject,
            $teacher,
            $validated['schedule_summary'] ?? null
        );

        return redirect()
            ->route('admin.assignments.index', ['school_year_id' => $section->school_year_id])
            ->with('status', "{$teacher->full_name} assigned to teach {$subject->name} in {$section->gradeLevel->name} – {$section->name}.");
    }

    public function deactivate(Request $request, TeacherAssignment $teacherAssignment): RedirectResponse
    {
        $syId = $teacherAssignment->school_year_id;
        $this->assignmentService->deactivateAssignment($teacherAssignment, $request->input('reason'));

        return redirect()
            ->route('admin.assignments.index', ['school_year_id' => $syId])
            ->with('status', 'Subject teacher assignment removed (historical records preserved).');
    }

    public function cloneFromSchoolYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'target_school_year_id' => ['required', 'integer', 'exists:school_years,id', 'different:source_school_year_id'],
        ]);

        $sourceSy = SchoolYear::query()->findOrFail($validated['source_school_year_id']);
        $targetSy = SchoolYear::query()->findOrFail($validated['target_school_year_id']);

        $sourceSections = Section::query()
            ->with(['adviser', 'activeTeacherAssignments.teacher', 'activeTeacherAssignments.subject'])
            ->where('school_year_id', $sourceSy->id)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (Section $s) => $s->grade_level_id . ':' . mb_strtolower(trim($s->name)));

        $targetSections = Section::query()
            ->with(['gradeLevel', 'activeTeacherAssignments'])
            ->where('school_year_id', $targetSy->id)
            ->where('is_active', true)
            ->get();

        $advisersCopied = 0;
        $subjectsCopied = 0;

        foreach ($targetSections as $targetSec) {
            $key = $targetSec->grade_level_id . ':' . mb_strtolower(trim($targetSec->name));
            $sourceSec = $sourceSections->get($key);
            if (! $sourceSec) {
                continue;
            }

            if ($targetSec->adviser_id === null && $sourceSec->adviser && $sourceSec->adviser->is_active) {
                $this->assignmentService->assignAdviser($targetSec, $sourceSec->adviser);
                $advisersCopied++;
            }

            $targetCurriculum = $this->curriculumResolver->resolveFor($targetSec->school_year_id, $targetSec->grade_level_id);
            if (! $targetCurriculum) {
                continue;
            }

            $existingSubjectIds = $targetSec->activeTeacherAssignments->pluck('subject_id')->all();

            foreach ($sourceSec->activeTeacherAssignments as $srcTa) {
                if (! $srcTa->teacher || ! $srcTa->teacher->is_active || ! $srcTa->subject) {
                    continue;
                }
                if ((int) $srcTa->subject->curriculum_id !== (int) $targetCurriculum->id) {
                    continue;
                }
                if (in_array((int) $srcTa->subject_id, $existingSubjectIds, true)) {
                    continue;
                }

                $this->assignmentService->assignSubjectTeacher(
                    $targetSec,
                    $srcTa->subject,
                    $srcTa->teacher,
                    $srcTa->schedule_summary
                );
                $subjectsCopied++;
            }
        }

        return redirect()
            ->route('admin.assignments.index', ['school_year_id' => $targetSy->id])
            ->with('status', "Copied {$advisersCopied} adviser(s) and {$subjectsCopied} subject load(s) from SY {$sourceSy->name} into SY {$targetSy->name}.");
    }
}
