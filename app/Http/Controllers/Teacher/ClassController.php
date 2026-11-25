<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\QuarterlyGrade;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Services\Grading\GradebookService;
use App\Services\Grading\GradeCalculationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassController extends Controller
{
    public function __construct(
        private readonly GradebookService $gradebookService,
        private readonly GradeCalculationService $calculator
    ) {
    }

    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;
        $activeSy = SchoolYear::current();

        $assignments = $teacher
            ? TeacherAssignment::query()
                ->with(['schoolYear.gradingPeriods', 'section.gradeLevel', 'section.adviser', 'subject.curriculum'])
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->when($activeSy, fn ($q) => $q->where('school_year_id', $activeSy->id))
                ->orderBy('section_id')
                ->orderBy('subject_id')
                ->get()
            : collect();

        $studentCountsBySection = $assignments->isNotEmpty()
            ? StudentEnrollment::query()
                ->whereIn('section_id', $assignments->pluck('section_id')->unique())
                ->where('is_current', true)
                ->selectRaw('section_id, COUNT(*) as cnt')
                ->groupBy('section_id')
                ->pluck('cnt', 'section_id')
            : collect();

        $periodStatuses = [];
        foreach ($assignments as $assignment) {
            foreach ($assignment->schoolYear->gradingPeriods as $period) {
                $sub = $this->gradebookService->resolveEffectiveSubmission($assignment, $period);
                $periodStatuses[$assignment->id][$period->id] = $sub?->status;
            }
        }

        return view('teacher.classes.index', [
            'assignments' => $assignments,
            'activeSy' => $activeSy,
            'studentCountsBySection' => $studentCountsBySection,
            'periodStatuses' => $periodStatuses,
        ]);
    }

    public function show(TeacherAssignment $teacherAssignment): View
    {
        $this->authorize('manage', $teacherAssignment);

        $teacherAssignment->load([
            'schoolYear.gradingPeriods',
            'section.gradeLevel',
            'section.adviser',
            'section.currentEnrollments.student',
            'subject.curriculum',
            'teacher',
        ]);

        $enrolledStudents = $teacherAssignment->section->currentEnrollments
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        return view('teacher.classes.show', [
            'assignment' => $teacherAssignment,
            'enrolledStudents' => $enrolledStudents,
        ]);
    }

    public function summary(TeacherAssignment $teacherAssignment): View
    {
        $this->authorize('view', $teacherAssignment);
        $data = $this->compileCumulativeSummary($teacherAssignment);

        return view('teacher.classes.summary', $data);
    }

    public function exportSummary(TeacherAssignment $teacherAssignment): StreamedResponse
    {
        $this->authorize('view', $teacherAssignment);
        $data = $this->compileCumulativeSummary($teacherAssignment);
        $school = SchoolSetting::current();
        $assignment = $data['assignment'];
        $periods = $data['periods'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Full-Year Subject Summary');

        $sheet->fromArray([
            [$school->school_name . ' — Cumulative Full-Year Subject Grade Summary'],
            ["Subject: {$assignment->subject->code} – {$assignment->subject->name} | Section: {$assignment->section->gradeLevel->name} – {$assignment->section->name}"],
            ["School Year: {$assignment->schoolYear->name} | Subject Teacher: {$assignment->teacher->full_name}"],
            [],
        ], null, 'A1');

        $headers = ['#', 'Student ID', 'LRN', 'Learner Name'];
        foreach ($periods as $p) {
            $headers[] = $p->code;
        }
        $headers = array_merge($headers, ['Final Subject Grade', 'Remarks']);
        $sheet->fromArray([$headers], null, 'A5');

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A5:{$lastCol}5")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A5:{$lastCol}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');

        $r = 6;
        foreach ($data['rows'] as $idx => $row) {
            $line = [
                $idx + 1,
                $row['student']->student_number,
                $row['student']->lrn ?? '',
                $row['student']->formal_name,
            ];
            foreach ($periods as $p) {
                $line[] = $row['period_grades'][$p->id]['display'] ?? '—';
            }
            $line[] = $row['final_grade'] !== null ? number_format($row['final_grade'], 0) : '—';
            $line[] = $row['remarks'];

            $sheet->fromArray([$line], null, "A{$r}");
            $r++;
        }

        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $safeCode = preg_replace('/[^A-Za-z0-9_-]+/', '_', $assignment->subject->code . '_' . $assignment->section->name);
        $filename = "cumulative_subject_summary_{$safeCode}_SY_{$assignment->schoolYear->name}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function printLoad(Request $request): View
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher !== null, 404, 'No faculty profile associated with this account.');

        $activeSy = SchoolYear::current();

        $assignments = TeacherAssignment::query()
            ->with(['schoolYear', 'section.gradeLevel', 'section.adviser', 'subject.curriculum'])
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->when($activeSy, fn ($q) => $q->where('school_year_id', $activeSy->id))
            ->orderBy('section_id')
            ->orderBy('subject_id')
            ->get();

        $studentCounts = $assignments->isNotEmpty()
            ? StudentEnrollment::query()
                ->whereIn('section_id', $assignments->pluck('section_id')->unique())
                ->where('is_current', true)
                ->selectRaw('section_id, COUNT(*) as cnt')
                ->groupBy('section_id')
                ->pluck('cnt', 'section_id')
            : collect();

        $advisorySections = Section::query()
            ->with(['gradeLevel', 'schoolYear'])
            ->withCount('currentEnrollments')
            ->where('adviser_id', $teacher->id)
            ->when($activeSy, fn ($q) => $q->where('school_year_id', $activeSy->id))
            ->orderBy('grade_level_id')
            ->get();

        return view('teacher.classes.print-load', [
            'school' => SchoolSetting::current(),
            'teacher' => $teacher->load('user.roles'),
            'activeSy' => $activeSy,
            'assignments' => $assignments,
            'studentCounts' => $studentCounts,
            'advisorySections' => $advisorySections,
        ]);
    }

    public function printRoster(TeacherAssignment $teacherAssignment): View
    {
        $this->authorize('view', $teacherAssignment);

        $teacherAssignment->load([
            'schoolYear',
            'section.gradeLevel',
            'section.adviser',
            'section.currentEnrollments.student',
            'subject',
            'teacher',
        ]);

        $enrollments = $teacherAssignment->section->currentEnrollments
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        return view('reports.class-roster-print', [
            'school' => SchoolSetting::current(),
            'section' => $teacherAssignment->section,
            'enrollments' => $enrollments,
            'subjectLabel' => "{$teacherAssignment->subject->code} – {$teacherAssignment->subject->name}",
            'teacherName' => $teacherAssignment->teacher->full_name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function compileCumulativeSummary(TeacherAssignment $assignment): array
    {
        $assignment->loadMissing([
            'schoolYear.gradingPeriods',
            'section.gradeLevel',
            'section.adviser',
            'subject.curriculum',
            'teacher',
        ]);

        $periods = $assignment->schoolYear->gradingPeriods;
        $config = $this->gradebookService->resolveConfiguration($assignment);
        $passingGrade = (float) ($config?->passing_grade ?? 75.0);

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $allGrades = QuarterlyGrade::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('subject_id', $assignment->subject_id)
            ->whereIn('grading_period_id', $periods->pluck('id'))
            ->get()
            ->keyBy(fn (QuarterlyGrade $g) => $g->student_enrollment_id . ':' . $g->grading_period_id);

        $rows = [];
        $finalGrades = [];
        $passedCount = 0;
        $failedCount = 0;

        foreach ($enrollments as $enr) {
            $periodGrades = [];
            $numericList = [];
            $complete = true;

            foreach ($periods as $p) {
                /** @var QuarterlyGrade|null $qg */
                $qg = $allGrades->get($enr->id . ':' . $p->id);
                if (! $qg || ($qg->final_grade === null && $qg->descriptive_rating === null)) {
                    $complete = false;
                    $periodGrades[$p->id] = ['display' => null, 'is_official' => false, 'numeric' => null];
                } else {
                    $periodGrades[$p->id] = [
                        'display' => $qg->display_grade,
                        'is_official' => (bool) $qg->is_official,
                        'numeric' => $qg->final_grade !== null ? (float) $qg->final_grade : null,
                    ];
                    if ($qg->final_grade !== null) {
                        $numericList[] = (float) $qg->final_grade;
                    }
                }
            }

            $finalGrade = ($complete && count($numericList) === $periods->count() && $periods->isNotEmpty())
                ? $this->calculator->computeFinalSubjectGrade($numericList, 0)
                : null;

            if ($finalGrade !== null) {
                $finalGrades[] = $finalGrade;
                if ($finalGrade >= $passingGrade) {
                    $passedCount++;
                    $remarks = 'Passed';
                } else {
                    $failedCount++;
                    $remarks = 'Did Not Meet Expectations';
                }
            } else {
                $remarks = $complete && $config?->isDescriptive() ? 'Completed (Descriptive)' : 'In Progress';
            }

            $rows[] = [
                'enrollment' => $enr,
                'student' => $enr->student,
                'period_grades' => $periodGrades,
                'final_grade' => $finalGrade,
                'remarks' => $remarks,
            ];
        }

        $evalCount = $passedCount + $failedCount;

        return [
            'assignment' => $assignment,
            'periods' => $periods,
            'config' => $config,
            'passingGrade' => $passingGrade,
            'rows' => collect($rows),
            'stats' => [
                'total_students' => $enrollments->count(),
                'completed_count' => $evalCount,
                'average' => ! empty($finalGrades) ? round(array_sum($finalGrades) / count($finalGrades), 2) : null,
                'highest' => ! empty($finalGrades) ? max($finalGrades) : null,
                'lowest' => ! empty($finalGrades) ? min($finalGrades) : null,
                'passed_count' => $passedCount,
                'failed_count' => $failedCount,
                'passing_rate' => $evalCount > 0 ? round(($passedCount / $evalCount) * 100, 1) : null,
            ],
        ];
    }
}
