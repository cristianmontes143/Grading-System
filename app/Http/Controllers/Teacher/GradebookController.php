<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\SchoolSetting;
use App\Models\StudentAssessmentScore;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Services\Grading\GradebookService;
use App\Services\Grading\GradeSubmissionService;
use App\Services\Imports\ScoreImportService;
use App\Services\Reports\GradeSheetExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradebookController extends Controller
{
    public function __construct(
        private readonly GradebookService $gradebookService,
        private readonly GradeSubmissionService $submissionService,
        private readonly ScoreImportService $scoreImportService,
        private readonly GradeSheetExportService $exportService
    ) {
    }

    public function show(TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): View
    {
        $this->authorize('manage', $teacherAssignment);
        abort_unless((int) $gradingPeriod->school_year_id === (int) $teacherAssignment->school_year_id, 404);

        $teacherAssignment->load([
            'schoolYear.gradingPeriods',
            'section.gradeLevel',
            'section.adviser',
            'subject.curriculum',
            'teacher',
        ]);

        $config = $this->gradebookService->resolveConfiguration($teacherAssignment);
        $leafComponents = $config ? $this->gradebookService->getLeafComponents($config) : collect();

        $submission = $this->gradebookService->resolveEffectiveSubmission($teacherAssignment, $gradingPeriod);
        $isPriorTeacherSubmission = $submission !== null
            && (int) $submission->teacher_assignment_id !== (int) $teacherAssignment->id;

        $displayAssignmentId = $isPriorTeacherSubmission
            ? $submission->teacher_assignment_id
            : $teacherAssignment->id;

        $assessments = Assessment::query()
            ->with('component')
            ->where('teacher_assignment_id', $displayAssignmentId)
            ->where('grading_period_id', $gradingPeriod->id)
            ->orderBy('grading_component_id')
            ->orderBy('id')
            ->get();

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $teacherAssignment->section_id)
            ->where('school_year_id', $teacherAssignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $scoresByKey = StudentAssessmentScore::query()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy(fn ($s) => $s->student_enrollment_id . ':' . $s->assessment_id);

        $gradesByEnrollment = QuarterlyGrade::query()
            ->with('teacherAssignment.teacher')
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('subject_id', $teacherAssignment->subject_id)
            ->where('grading_period_id', $gradingPeriod->id)
            ->get()
            ->keyBy('student_enrollment_id');

        $isLocked = ! $gradingPeriod->isOpen()
            || $teacherAssignment->schoolYear->is_closed
            || ($submission && $submission->isOfficial())
            || $gradesByEnrollment->contains(fn ($g) => $g->is_locked);

        $numericFinalGrades = $gradesByEnrollment->pluck('final_grade')->filter(fn ($g) => $g !== null)->map(fn ($g) => (float) $g)->values();
        $classAverage = $numericFinalGrades->isNotEmpty() ? $numericFinalGrades->avg() : null;
        $passingThreshold = (float) ($config?->passing_grade ?? 75.0);

        $gradeStats = null;
        if ($numericFinalGrades->isNotEmpty()) {
            $passed = $numericFinalGrades->filter(fn ($g) => $g >= $passingThreshold)->count();
            $failed = $numericFinalGrades->count() - $passed;
            $gradeStats = [
                'graded_count' => $numericFinalGrades->count(),
                'highest' => round((float) $numericFinalGrades->max(), 2),
                'lowest' => round((float) $numericFinalGrades->min(), 2),
                'passed_count' => $passed,
                'failed_count' => $failed,
                'passing_rate' => round(($passed / $numericFinalGrades->count()) * 100, 1),
                'bands' => [
                    'outstanding' => $numericFinalGrades->filter(fn ($g) => $g >= 90)->count(),
                    'very_satisfactory' => $numericFinalGrades->filter(fn ($g) => $g >= 85 && $g < 90)->count(),
                    'satisfactory' => $numericFinalGrades->filter(fn ($g) => $g >= 80 && $g < 85)->count(),
                    'fairly_satisfactory' => $numericFinalGrades->filter(fn ($g) => $g >= 75 && $g < 80)->count(),
                    'did_not_meet' => $numericFinalGrades->filter(fn ($g) => $g < 75)->count(),
                ],
            ];
        }

        $cloneSources = (! $isLocked && $config && ! $config->isDescriptive())
            ? TeacherAssignment::query()
                ->with(['section.gradeLevel', 'subject'])
                ->where('teacher_id', $teacherAssignment->teacher_id)
                ->where('school_year_id', $teacherAssignment->school_year_id)
                ->where('is_active', true)
                ->orderBy('section_id')
                ->get()
                ->filter(fn (TeacherAssignment $candidate) => (int) $this->gradebookService->resolveConfiguration($candidate)?->id === (int) $config->id)
                ->values()
            : collect();

        return view('teacher.gradebook.show', [
            'assignment' => $teacherAssignment,
            'period' => $gradingPeriod,
            'config' => $config,
            'leafComponents' => $leafComponents,
            'assessments' => $assessments,
            'enrollments' => $enrollments,
            'scoresByKey' => $scoresByKey,
            'gradesByEnrollment' => $gradesByEnrollment,
            'submission' => $submission,
            'isPriorTeacherSubmission' => $isPriorTeacherSubmission,
            'isLocked' => $isLocked,
            'classAverage' => $classAverage !== null ? round($classAverage, 2) : null,
            'gradeStats' => $gradeStats,
            'cloneSources' => $cloneSources,
        ]);
    }

    public function submitGrades(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $this->submissionService->submit($teacherAssignment, $gradingPeriod, $request->user());

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', "{$gradingPeriod->name} official grades have been submitted and sent automatically to the Section Adviser.");
    }

    public function storeAssessment(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $validated = $request->validate([
            'grading_component_id' => ['required', 'integer', 'exists:grading_components,id'],
            'title' => ['required', 'string', 'max:150'],
            'assessment_type' => ['required', Rule::in([
                'quiz', 'written_activity', 'performance_task', 'project', 'summative_test', 'examination', 'other',
            ])],
            'max_score' => ['required', 'numeric', 'gt:0', 'lte:10000'],
            'assessment_date' => ['nullable', 'date'],
        ]);

        $this->gradebookService->createAssessment($teacherAssignment, $gradingPeriod, $validated, $request->user());

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', "Assessment \"{$validated['title']}\" added.");
    }

    public function deleteAssessment(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod, Assessment $assessment): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $this->gradebookService->deleteAssessment($teacherAssignment, $gradingPeriod, $assessment, $request->user());

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', 'Assessment removed and grades recalculated.');
    }

    public function updateAssessment(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod, Assessment $assessment): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'assessment_type' => ['required', Rule::in([
                'quiz', 'written_activity', 'performance_task', 'project', 'summative_test', 'examination', 'other',
            ])],
            'max_score' => ['required', 'numeric', 'gt:0', 'lte:10000'],
            'assessment_date' => ['nullable', 'date'],
        ]);

        $this->gradebookService->updateAssessment($teacherAssignment, $gradingPeriod, $assessment, $validated, $request->user());

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', "Assessment \"{$validated['title']}\" updated and grades recalculated.");
    }

    public function cloneAssessments(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $validated = $request->validate([
            'source_assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'source_period_id' => ['required', 'integer', 'exists:grading_periods,id'],
        ]);

        $sourceAssignment = TeacherAssignment::query()->findOrFail($validated['source_assignment_id']);
        $sourcePeriod = GradingPeriod::query()->findOrFail($validated['source_period_id']);

        $count = $this->gradebookService->cloneAssessmentsFrom(
            $teacherAssignment,
            $gradingPeriod,
            $sourceAssignment,
            $sourcePeriod,
            $request->user()
        );

        $msg = $count > 0
            ? "Copied {$count} assessment(s) into {$gradingPeriod->code} and initialized grade rows."
            : 'All assessments from the selected source already exist in this gradebook.';

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', $msg);
    }

    public function saveScores(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $scores = $request->input('scores', []);
        $updated = $this->gradebookService->saveBulkScores($teacherAssignment, $gradingPeriod, $scores, $request->user());

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', "Saved {$updated} score update(s) and automatically recomputed grades.");
    }

    public function saveDescriptive(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $ratings = $request->input('ratings', []);
        $updated = $this->gradebookService->saveDescriptiveRatings($teacherAssignment, $gradingPeriod, $ratings, $request->user());

        return redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', "Saved {$updated} descriptive rating update(s).");
    }

    public function downloadScoreTemplate(TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): StreamedResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $spreadsheet = $this->scoreImportService->buildTemplateSpreadsheet($teacherAssignment, $gradingPeriod);
        $filename = "scores_template_{$teacherAssignment->subject->code}_{$gradingPeriod->code}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importScores(Request $request, TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): RedirectResponse
    {
        $this->authorize('manage', $teacherAssignment);

        $request->validate([
            'score_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $result = $this->scoreImportService->import($request->file('score_file'), $teacherAssignment, $gradingPeriod, $request->user());

        $redirect = redirect()
            ->route('teacher.gradebook.show', [$teacherAssignment, $gradingPeriod])
            ->with('status', "Score import completed: {$result['updated']} score(s) updated.");

        if (! empty($result['errors'])) {
            $redirect->with('import_errors', $result['errors']);
        }

        return $redirect;
    }

    public function printGradeSheet(TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): View
    {
        $this->authorize('view', $teacherAssignment);
        abort_unless((int) $gradingPeriod->school_year_id === (int) $teacherAssignment->school_year_id, 404);

        $teacherAssignment->load(['schoolYear', 'section.gradeLevel', 'section.adviser', 'subject', 'teacher']);

        $submission = $this->gradebookService->resolveEffectiveSubmission($teacherAssignment, $gradingPeriod);
        $displayAssignmentId = ($submission && (int) $submission->teacher_assignment_id !== (int) $teacherAssignment->id)
            ? $submission->teacher_assignment_id
            : $teacherAssignment->id;

        $assessments = Assessment::query()
            ->with('component')
            ->where('teacher_assignment_id', $displayAssignmentId)
            ->where('grading_period_id', $gradingPeriod->id)
            ->orderBy('grading_component_id')
            ->orderBy('id')
            ->get();

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $teacherAssignment->section_id)
            ->where('school_year_id', $teacherAssignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $scoresByKey = StudentAssessmentScore::query()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy(fn ($s) => $s->student_enrollment_id . ':' . $s->assessment_id);

        $gradesByEnrollment = QuarterlyGrade::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('subject_id', $teacherAssignment->subject_id)
            ->where('grading_period_id', $gradingPeriod->id)
            ->get()
            ->keyBy('student_enrollment_id');

        return view('teacher.gradebook.print', [
            'school' => SchoolSetting::current(),
            'assignment' => $teacherAssignment,
            'period' => $gradingPeriod,
            'assessments' => $assessments,
            'enrollments' => $enrollments,
            'scoresByKey' => $scoresByKey,
            'gradesByEnrollment' => $gradesByEnrollment,
        ]);
    }

    public function exportGradeSheet(TeacherAssignment $teacherAssignment, GradingPeriod $gradingPeriod): StreamedResponse
    {
        $this->authorize('view', $teacherAssignment);
        abort_unless((int) $gradingPeriod->school_year_id === (int) $teacherAssignment->school_year_id, 404);

        $spreadsheet = $this->exportService->buildGradeSheetSpreadsheet($teacherAssignment, $gradingPeriod);
        $filename = "grade_sheet_{$teacherAssignment->subject->code}_{$teacherAssignment->section->name}_{$gradingPeriod->code}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
