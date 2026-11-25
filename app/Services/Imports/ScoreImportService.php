<?php

namespace App\Services\Imports;

use App\Models\Assessment;
use App\Models\GradingPeriod;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Grading\GradebookService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ScoreImportService
{
    public function __construct(private readonly GradebookService $gradebookService)
    {
    }

    public function buildTemplateSpreadsheet(TeacherAssignment $assignment, GradingPeriod $period): Spreadsheet
    {
        $assessments = Assessment::query()
            ->with('component')
            ->where('teacher_assignment_id', $assignment->id)
            ->where('grading_period_id', $period->id)
            ->orderBy('grading_component_id')
            ->orderBy('id')
            ->get();

        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Scores Template');

        $headers = ['student_number', 'learner_name'];
        foreach ($assessments as $assessment) {
            $headers[] = "A#{$assessment->id}: {$assessment->title} (Max {$assessment->max_score})";
        }
        $sheet->fromArray([$headers], null, 'A1');

        $rowIdx = 2;
        foreach ($enrollments as $enr) {
            $row = [$enr->student->student_number, $enr->student->formal_name];
            $sheet->fromArray([$row], null, "A{$rowIdx}");
            $rowIdx++;
        }

        return $spreadsheet;
    }

    /**
     * Import assessment scores from Excel/CSV.
     *
     * @return array{updated: int, errors: array<int, string>}
     */
    public function import(
        UploadedFile $file,
        TeacherAssignment $assignment,
        GradingPeriod $period,
        User $actor
    ): array {
        $this->gradebookService->ensureEditable($assignment, $period);

        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return ['updated' => 0, 'errors' => ['Uploaded score sheet has no data rows.']];
        }

        $headerRow = $rows[0];
        $studentNumberCol = null;
        $assessmentColMap = []; // colIndex => assessmentId

        foreach ($headerRow as $colIdx => $label) {
            $text = trim((string) $label);
            if (strtolower($text) === 'student_number') {
                $studentNumberCol = $colIdx;
                continue;
            }
            if (preg_match('/^A#(\d+)/i', $text, $matches)) {
                $assessmentColMap[$colIdx] = (int) $matches[1];
            }
        }

        if ($studentNumberCol === null || empty($assessmentColMap)) {
            return [
                'updated' => 0,
                'errors' => ['Invalid template format. Download the official Score Import Template for this class first.'],
            ];
        }

        $enrollmentsByStudentNo = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $assignment->section_id)
            ->where('school_year_id', $assignment->school_year_id)
            ->where('is_current', true)
            ->get()
            ->keyBy(fn (StudentEnrollment $e) => strtoupper($e->student->student_number));

        $scoresMatrix = [];
        $errors = [];

        foreach (array_slice($rows, 1) as $idx => $row) {
            $rowNumber = $idx + 2;
            $studentNo = strtoupper(trim((string) ($row[$studentNumberCol] ?? '')));
            if ($studentNo === '') {
                continue;
            }

            $enrollment = $enrollmentsByStudentNo->get($studentNo);
            if (! $enrollment) {
                $errors[] = "Row {$rowNumber}: Student ID '{$studentNo}' is not currently enrolled in this section.";
                continue;
            }

            foreach ($assessmentColMap as $colIdx => $assessmentId) {
                $scoresMatrix[$enrollment->id][$assessmentId] = $row[$colIdx] ?? '';
            }
        }

        $updated = $this->gradebookService->saveBulkScores(
            $assignment,
            $period,
            $scoresMatrix,
            $actor,
            'Bulk Excel/CSV score import'
        );

        return ['updated' => $updated, 'errors' => $errors];
    }
}
