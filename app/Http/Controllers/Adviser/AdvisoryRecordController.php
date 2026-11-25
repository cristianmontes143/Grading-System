<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\StudentObservedValue;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvisoryRecordController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function edit(Request $request, Section $section): View
    {
        $this->authorize('viewAdvisory', $section);

        $section->load(['schoolYear.gradingPeriods', 'gradeLevel', 'currentEnrollments.student']);
        $periods = $section->schoolYear->gradingPeriods;
        $selectedPeriod = $periods->firstWhere('id', $request->integer('period_id'))
            ?? $periods->firstWhere('status', GradingPeriod::STATUS_OPEN)
            ?? $periods->first();

        $month = $request->integer('month') ?: (int) now()->month;
        $year = $request->integer('year') ?: (int) now()->year;

        $enrollments = $section->currentEnrollments
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $attendanceByEnrollment = AttendanceRecord::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('month_number', $month)
            ->where('year_number', $year)
            ->get()
            ->keyBy('student_enrollment_id');

        $valuesByKey = $selectedPeriod
            ? StudentObservedValue::query()
                ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->where('grading_period_id', $selectedPeriod->id)
                ->get()
                ->keyBy(fn ($v) => $v->student_enrollment_id . ':' . $v->behavior_statement_code)
            : collect();

        return view('adviser.records.edit', [
            'section' => $section,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'month' => $month,
            'year' => $year,
            'enrollments' => $enrollments,
            'attendanceByEnrollment' => $attendanceByEnrollment,
            'valuesByKey' => $valuesByKey,
            'statements' => StudentObservedValue::CORE_VALUE_STATEMENTS,
            'ratingScale' => StudentObservedValue::RATING_SCALE,
        ]);
    }

    public function saveAttendance(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('viewAdvisory', $section);

        $validated = $request->validate([
            'month_number' => ['required', 'integer', 'min:1', 'max:12'],
            'year_number' => ['required', 'integer', 'min:2020', 'max:2100'],
            'attendance' => ['required', 'array'],
            'attendance.*.school_days' => ['required', 'numeric', 'min:0', 'max:31'],
            'attendance.*.days_present' => ['required', 'numeric', 'min:0', 'max:31'],
            'attendance.*.days_absent' => ['required', 'numeric', 'min:0', 'max:31'],
            'attendance.*.days_tardy' => ['required', 'numeric', 'min:0', 'max:31'],
        ]);

        $validEnrollmentIds = StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->where('is_current', true)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($validated, $validEnrollmentIds, $request): void {
            foreach ($validated['attendance'] as $enrollmentId => $row) {
                if (! in_array((int) $enrollmentId, $validEnrollmentIds, true)) {
                    continue;
                }

                AttendanceRecord::query()->updateOrCreate(
                    [
                        'student_enrollment_id' => (int) $enrollmentId,
                        'year_number' => $validated['year_number'],
                        'month_number' => $validated['month_number'],
                    ],
                    [
                        'school_days' => $row['school_days'],
                        'days_present' => $row['days_present'],
                        'days_absent' => $row['days_absent'],
                        'days_tardy' => $row['days_tardy'],
                        'recorded_by' => $request->user()->id,
                    ]
                );
            }
        });

        $this->auditLog->record(
            eventType: 'attendance.updated',
            auditable: $section,
            newValues: ['month' => $validated['month_number'], 'year' => $validated['year_number']],
            request: $request
        );

        return back()->with('status', 'Monthly attendance records saved.');
    }

    public function saveObservedValues(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('viewAdvisory', $section);

        $validated = $request->validate([
            'grading_period_id' => ['required', 'exists:grading_periods,id'],
            'values' => ['required', 'array'],
            'values.*.*' => ['nullable', Rule::in(['AO', 'SO', 'RO', 'NO'])],
        ]);

        $period = GradingPeriod::query()->findOrFail($validated['grading_period_id']);
        abort_unless((int) $period->school_year_id === (int) $section->school_year_id, 404);

        $validEnrollmentIds = StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->where('is_current', true)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($validated, $validEnrollmentIds, $period, $request): void {
            foreach ($validated['values'] as $enrollmentId => $codes) {
                if (! in_array((int) $enrollmentId, $validEnrollmentIds, true) || ! is_array($codes)) {
                    continue;
                }

                foreach ($codes as $code => $rating) {
                    if (empty($rating) || ! isset(StudentObservedValue::CORE_VALUE_STATEMENTS[$code])) {
                        continue;
                    }

                    StudentObservedValue::query()->updateOrCreate(
                        [
                            'student_enrollment_id' => (int) $enrollmentId,
                            'grading_period_id' => $period->id,
                            'behavior_statement_code' => $code,
                        ],
                        [
                            'core_value' => StudentObservedValue::CORE_VALUE_STATEMENTS[$code]['core_value'],
                            'rating' => $rating,
                            'recorded_by' => $request->user()->id,
                        ]
                    );
                }
            }
        });

        $this->auditLog->record(
            eventType: 'observed_values.updated',
            auditable: $section,
            newValues: ['grading_period_id' => $period->id],
            request: $request
        );

        return back()->with('status', "DepEd Core Observed Values saved for {$period->name}.");
    }

    public function exportAttendance(Section $section): StreamedResponse
    {
        $this->authorize('viewAdvisory', $section);
        $section->loadMissing(['schoolYear', 'gradeLevel', 'adviser', 'currentEnrollments.student']);

        $enrollments = $section->currentEnrollments
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        $allAttendance = AttendanceRecord::query()
            ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->orderBy('year_number')
            ->orderBy('month_number')
            ->get();

        $months = $allAttendance
            ->map(fn ($a) => sprintf('%04d-%02d', $a->year_number, $a->month_number))
            ->unique()
            ->values();

        $byKey = $allAttendance->keyBy(fn ($a) => $a->student_enrollment_id . ':' . sprintf('%04d-%02d', $a->year_number, $a->month_number));
        $school = SchoolSetting::current();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Summary');

        $sheet->fromArray([
            [$school->school_name . ' — Section Attendance Summary (SF2 Rollup)'],
            ["Section: {$section->gradeLevel->name} – {$section->name} | SY {$section->schoolYear->name} | Adviser: " . ($section->adviser?->full_name ?? 'Unassigned')],
            [],
        ], null, 'A1');

        $headers = ['#', 'Student ID', 'LRN', 'Learner Name'];
        foreach ($months as $ym) {
            [$y, $m] = explode('-', $ym);
            $headers[] = \Carbon\Carbon::create((int) $y, (int) $m, 1)->format('M Y') . ' (P/D)';
        }
        $headers = array_merge($headers, ['Total Days', 'Total Present', 'Total Absent', 'Total Tardy', 'Attendance %']);
        $sheet->fromArray([$headers], null, 'A4');

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A4:{$lastCol}4")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A4:{$lastCol}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');

        $rowNum = 5;
        foreach ($enrollments as $idx => $enr) {
            $row = [
                $idx + 1,
                $enr->student->student_number,
                $enr->student->lrn ?? '',
                $enr->student->formal_name,
            ];

            $totDays = 0.0;
            $totPresent = 0.0;
            $totAbsent = 0.0;
            $totTardy = 0.0;

            foreach ($months as $ym) {
                $rec = $byKey->get($enr->id . ':' . $ym);
                if ($rec) {
                    $row[] = "{$rec->days_present}/{$rec->school_days}";
                    $totDays += (float) $rec->school_days;
                    $totPresent += (float) $rec->days_present;
                    $totAbsent += (float) $rec->days_absent;
                    $totTardy += (float) $rec->days_tardy;
                } else {
                    $row[] = '—';
                }
            }

            $pct = $totDays > 0 ? round(($totPresent / $totDays) * 100, 1) . '%' : '—';
            $row = array_merge($row, [round($totDays, 1), round($totPresent, 1), round($totAbsent, 1), round($totTardy, 1), $pct]);

            $sheet->fromArray([$row], null, "A{$rowNum}");
            $rowNum++;
        }

        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $safeSec = preg_replace('/[^A-Za-z0-9_-]+/', '_', $section->gradeLevel->code . '_' . $section->name);
        $filename = "attendance_summary_{$safeSec}_SY_{$section->schoolYear->name}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
