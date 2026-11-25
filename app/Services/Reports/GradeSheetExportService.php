<?php

namespace App\Services\Reports;

use App\Models\Assessment;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\SchoolSetting;
use App\Models\StudentAssessmentScore;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class GradeSheetExportService
{
    public function buildGradeSheetSpreadsheet(TeacherAssignment $assignment, GradingPeriod $period): Spreadsheet
    {
        $assignment->loadMissing(['schoolYear', 'section.gradeLevel', 'section.adviser', 'subject', 'teacher']);
        $school = SchoolSetting::current();

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

        $scoresByKey = StudentAssessmentScore::query()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy(fn ($s) => $s->student_enrollment_id . ':' . $s->assessment_id);

        $gradesByEnrollment = QuarterlyGrade::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('subject_id', $assignment->subject_id)
            ->where('grading_period_id', $period->id)
            ->get()
            ->keyBy('student_enrollment_id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Subject Grade Sheet');

        $sheet->fromArray([
            [$school->school_name],
            ["Subject Grade Sheet — SY {$assignment->schoolYear->name} | {$period->name} ({$period->code})"],
            ["Section: {$assignment->section->gradeLevel->name} – {$assignment->section->name}", "Subject: {$assignment->subject->code} – {$assignment->subject->name}"],
            ["Subject Teacher: {$assignment->teacher->full_name}", 'Adviser: ' . ($assignment->section->adviser?->full_name ?? 'Unassigned')],
            [],
        ], null, 'A1');

        $headers = ['#', 'Student ID', 'LRN', 'Learner Name'];
        foreach ($assessments as $a) {
            $headers[] = "{$a->component->code}: {$a->title} ({$a->max_score})";
        }
        $headers = array_merge($headers, ['Initial Grade', 'Final / Term Grade', 'Descriptive', 'Remarks', 'Missing']);
        $sheet->fromArray([$headers], null, 'A6');

        $rowNum = 7;
        foreach ($enrollments as $idx => $enr) {
            $qg = $gradesByEnrollment->get($enr->id);
            $row = [
                $idx + 1,
                $enr->student->student_number,
                $enr->student->lrn ?? '',
                $enr->student->formal_name,
            ];

            foreach ($assessments as $a) {
                $sc = $scoresByKey->get($enr->id . ':' . $a->id);
                if (! $sc || $sc->status === 'missing') {
                    $row[] = 'M';
                } elseif ($sc->status === 'excused') {
                    $row[] = 'EX';
                } else {
                    $row[] = $sc->raw_score;
                }
            }

            $row[] = $qg?->initial_grade ?? '';
            $row[] = $qg?->final_grade ?? '';
            $row[] = $qg?->descriptive_rating ?? '';
            $row[] = $qg?->remarks ?? '';
            $row[] = $qg?->missing_scores_count ?? 0;

            $sheet->fromArray([$row], null, "A{$rowNum}");
            $rowNum++;
        }

        return $spreadsheet;
    }
}
