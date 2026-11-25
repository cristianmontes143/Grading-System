<?php

namespace App\Services\Imports;

use App\Models\Section;
use App\Models\Student;
use App\Services\Audit\AuditLogService;
use App\Services\Students\EnrollmentService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class StudentImportService
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
        private readonly AuditLogService $auditLog
    ) {
    }

    /**
     * Import students from CSV or Excel (.xlsx/.xls) with row-level validation.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file, ?Section $targetSection = null): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['The uploaded file must contain a header row and at least one student row.'],
            ];
        }

        $headers = array_map(
            fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', (string) $h))),
            $rows[0]
        );

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $rowNumber = $index + 2;

            // Skip completely empty rows
            $nonEmpty = array_filter($rawRow, fn ($v) => trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $data = [];
            foreach ($headers as $colIdx => $key) {
                if ($key !== '') {
                    $data[$key] = isset($rawRow[$colIdx]) ? trim((string) $rawRow[$colIdx]) : null;
                }
            }

            // Normalize Excel numeric date if applicable
            if (! empty($data['date_of_birth']) && is_numeric($data['date_of_birth'])) {
                try {
                    $data['date_of_birth'] = Carbon::instance(
                        ExcelDate::excelToDateTimeObject((float) $data['date_of_birth'])
                    )->toDateString();
                } catch (Throwable) {
                    // Let validator catch malformed date
                }
            }

            if (isset($data['lrn']) && $data['lrn'] === '') {
                $data['lrn'] = null;
            }

            $validator = Validator::make($data, [
                'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
                'lrn' => ['nullable', 'digits:12', 'unique:students,lrn'],
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'suffix' => ['nullable', 'string', 'max:20'],
                'gender' => ['required', 'in:Male,Female'],
                'date_of_birth' => ['required', 'date'],
                'address' => ['nullable', 'string', 'max:500'],
                'guardian_name' => ['nullable', 'string', 'max:150'],
                'guardian_contact' => ['nullable', 'string', 'max:50'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: " . implode(' ', $validator->errors()->all());
                continue;
            }

            try {
                DB::transaction(function () use ($validator, $targetSection): void {
                    $valid = $validator->validated();
                    $student = Student::query()->create([
                        'student_number' => strtoupper($valid['student_number']),
                        'lrn' => $valid['lrn'] ?? null,
                        'first_name' => $valid['first_name'],
                        'middle_name' => $valid['middle_name'] ?? null,
                        'last_name' => $valid['last_name'],
                        'suffix' => $valid['suffix'] ?? null,
                        'gender' => $valid['gender'],
                        'date_of_birth' => $valid['date_of_birth'],
                        'address' => $valid['address'] ?? null,
                        'guardian_name' => $valid['guardian_name'] ?? null,
                        'guardian_contact' => $valid['guardian_contact'] ?? null,
                        'status' => Student::STATUS_ACTIVE,
                    ]);

                    if ($targetSection) {
                        $this->enrollmentService->enrollStudent($student, $targetSection);
                    }
                });
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
            }
        }

        $this->auditLog->record(
            eventType: 'students.bulk_imported',
            newValues: [
                'filename' => $file->getClientOriginalName(),
                'imported' => $imported,
                'skipped' => $skipped,
                'target_section_id' => $targetSection?->id,
            ]
        );

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
