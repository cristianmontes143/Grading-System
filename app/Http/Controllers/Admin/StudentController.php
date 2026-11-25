<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Audit\AuditLogService;
use App\Services\Imports\StudentImportService;
use App\Services\Reports\PermanentRecordService;
use App\Services\Students\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
        private readonly StudentImportService $importService,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function index(Request $request): View
    {
        $schoolYears = SchoolYear::query()->orderByDesc('start_date')->get();
        $activeSy = $schoolYears->firstWhere('is_active', true) ?? $schoolYears->first();
        $selectedSyId = $request->integer('school_year_id') ?: $activeSy?->id;

        $gradeLevels = GradeLevel::query()->active()->ordered()->get();
        $sections = Section::query()
            ->with('gradeLevel')
            ->when($selectedSyId, fn ($q) => $q->where('school_year_id', $selectedSyId))
            ->where('is_active', true)
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();

        $query = Student::query()
            ->with(['currentEnrollment.section.gradeLevel', 'currentEnrollment.schoolYear', 'currentEnrollment.curriculum'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'ilike', "%{$search}%")
                    ->orWhere('lrn', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', Student::STATUS_ARCHIVED);
        }

        if ($sectionId = $request->integer('section_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('section_id', $sectionId)->where('is_current', true));
        } elseif ($gradeLevelId = $request->integer('grade_level_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('grade_level_id', $gradeLevelId)->where('is_current', true));
        }

        $students = $query->paginate(20)->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'schoolYears' => $schoolYears,
            'selectedSyId' => $selectedSyId,
            'gradeLevels' => $gradeLevels,
            'sections' => $sections,
        ]);
    }

    public function sf10(Request $request, Student $student, PermanentRecordService $permanentRecordService): View|Response
    {
        if ($request->boolean('pdf')) {
            return $permanentRecordService->generatePdf($student);
        }

        return view('reports.sf10', $permanentRecordService->assembleForStudent($student));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Student::query()
            ->with(['currentEnrollment.section.gradeLevel', 'currentEnrollment.schoolYear', 'currentEnrollment.curriculum'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'ilike', "%{$search}%")
                    ->orWhere('lrn', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', Student::STATUS_ARCHIVED);
        }

        if ($sectionId = $request->integer('section_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('section_id', $sectionId)->where('is_current', true));
        } elseif ($gradeLevelId = $request->integer('grade_level_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('grade_level_id', $gradeLevelId)->where('is_current', true));
        }

        $students = $query->get();
        $school = SchoolSetting::current();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Student Master List');

        $sheet->fromArray([
            [$school->school_name . ' — Student Master List (SF1 Directory Export)'],
            ['Generated: ' . now()->format('F d, Y h:i A') . ' | Total Records: ' . $students->count()],
            [],
            ['#', 'Student ID', 'DepEd LRN', 'Last Name', 'First Name', 'Middle Name', 'Gender', 'Date of Birth', 'School Year', 'Grade & Section', 'Curriculum', 'Guardian', 'Contact', 'Status'],
        ], null, 'A1');

        $sheet->getStyle('A1:N1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A4:N4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:N4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');

        $rowNum = 5;
        foreach ($students as $idx => $s) {
            $enr = $s->currentEnrollment;
            $sheet->fromArray([[
                $idx + 1,
                $s->student_number,
                $s->lrn ?? '',
                $s->last_name,
                $s->first_name,
                $s->middle_name ?? '',
                $s->gender,
                $s->date_of_birth?->format('Y-m-d') ?? '',
                $enr?->schoolYear?->name ?? '',
                $enr ? ($enr->section->gradeLevel->name . ' – ' . $enr->section->name) : 'Unenrolled',
                $enr?->curriculum?->code ?? '',
                $s->guardian_name ?? '',
                $s->guardian_contact ?? '',
                ucwords(str_replace('_', ' ', $s->status)),
            ]], null, "A{$rowNum}");
            $rowNum++;
        }

        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $filename = 'student_master_list_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'lrn' => ['nullable', 'digits:12', 'unique:students,lrn'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_contact' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $student = DB::transaction(function () use ($validated, $request): Student {
            $photoPath = $request->hasFile('photo')
                ? $request->file('photo')->store('students', 'public')
                : null;

            $student = Student::query()->create([
                'student_number' => strtoupper(trim($validated['student_number'])),
                'lrn' => $validated['lrn'] ?? null,
                'first_name' => trim($validated['first_name']),
                'middle_name' => isset($validated['middle_name']) ? trim($validated['middle_name']) : null,
                'last_name' => trim($validated['last_name']),
                'suffix' => isset($validated['suffix']) ? trim($validated['suffix']) : null,
                'gender' => $validated['gender'],
                'date_of_birth' => $validated['date_of_birth'],
                'photo_path' => $photoPath,
                'address' => $validated['address'] ?? null,
                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_contact' => $validated['guardian_contact'] ?? null,
                'status' => Student::STATUS_ACTIVE,
            ]);

            if (! empty($validated['section_id'])) {
                $section = Section::query()->with(['schoolYear', 'gradeLevel'])->findOrFail($validated['section_id']);
                $this->enrollmentService->enrollStudent($student, $section);
            }

            return $student;
        });

        $this->auditLog->record(
            eventType: 'student.created',
            auditable: $student,
            newValues: $student->only(['student_number', 'lrn', 'first_name', 'last_name', 'gender']),
            request: $request,
            context: ['student_id' => $student->id]
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('status', "Student {$student->full_name} added successfully.");
    }

    public function show(Student $student): View
    {
        $student->load([
            'enrollments.schoolYear',
            'enrollments.gradeLevel',
            'enrollments.section.adviser',
            'enrollments.curriculum',
            'enrollments.shsTrack',
            'enrollments.shsStrand',
            'enrollments.transferredFrom.section',
        ]);

        $currentEnrollment = $student->enrollments->firstWhere('is_current', true);

        $transferTargetSections = $currentEnrollment
            ? Section::query()
                ->with('gradeLevel')
                ->where('school_year_id', $currentEnrollment->school_year_id)
                ->where('id', '!=', $currentEnrollment->section_id)
                ->where('is_active', true)
                ->orderBy('grade_level_id')
                ->orderBy('name')
                ->get()
            : collect();

        $allActiveSections = Section::query()
            ->with(['schoolYear', 'gradeLevel'])
            ->where('is_active', true)
            ->orderByDesc('school_year_id')
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();

        return view('admin.students.show', [
            'student' => $student,
            'currentEnrollment' => $currentEnrollment,
            'transferTargetSections' => $transferTargetSections,
            'allActiveSections' => $allActiveSections,
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($student->id)],
            'lrn' => ['nullable', 'digits:12', Rule::unique('students', 'lrn')->ignore($student->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_contact' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([
                Student::STATUS_ACTIVE,
                Student::STATUS_TRANSFERRED_OUT,
                Student::STATUS_GRADUATED,
                Student::STATUS_DROPPED,
                Student::STATUS_ARCHIVED,
            ])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldValues = $student->only(['student_number', 'lrn', 'first_name', 'last_name', 'status']);

        $student->fill([
            'student_number' => strtoupper(trim($validated['student_number'])),
            'lrn' => $validated['lrn'] ?? null,
            'first_name' => trim($validated['first_name']),
            'middle_name' => isset($validated['middle_name']) ? trim($validated['middle_name']) : null,
            'last_name' => trim($validated['last_name']),
            'suffix' => isset($validated['suffix']) ? trim($validated['suffix']) : null,
            'gender' => $validated['gender'],
            'date_of_birth' => $validated['date_of_birth'],
            'address' => $validated['address'] ?? null,
            'guardian_name' => $validated['guardian_name'] ?? null,
            'guardian_contact' => $validated['guardian_contact'] ?? null,
            'status' => $validated['status'],
        ]);

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('public')->delete($student->photo_path);
            }
            $student->photo_path = $request->file('photo')->store('students', 'public');
        }

        $student->save();

        $this->auditLog->record(
            eventType: 'student.updated',
            auditable: $student,
            oldValues: $oldValues,
            newValues: $student->only(['student_number', 'lrn', 'first_name', 'last_name', 'status']),
            request: $request,
            context: ['student_id' => $student->id]
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('status', 'Student profile updated.');
    }

    public function archive(Request $request, Student $student): RedirectResponse
    {
        $oldStatus = $student->status;
        $newStatus = $oldStatus === Student::STATUS_ARCHIVED ? Student::STATUS_ACTIVE : Student::STATUS_ARCHIVED;
        $student->update(['status' => $newStatus]);

        $this->auditLog->record(
            eventType: $newStatus === Student::STATUS_ARCHIVED ? 'student.archived' : 'student.restored',
            auditable: $student,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            request: $request,
            context: ['student_id' => $student->id]
        );

        return redirect()
            ->route('admin.students.index')
            ->with('status', "Student {$student->full_name} has been " . ($newStatus === Student::STATUS_ARCHIVED ? 'archived' : 'restored') . '.');
    }

    public function enroll(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'enrolled_at' => ['nullable', 'date'],
        ]);

        $section = Section::query()->with(['schoolYear', 'gradeLevel'])->findOrFail($validated['section_id']);
        $this->enrollmentService->enrollStudent($student, $section, $validated['enrolled_at'] ?? null);

        return redirect()
            ->route('admin.students.show', $student)
            ->with('status', "Enrolled {$student->full_name} into {$section->gradeLevel->name} – {$section->name} (SY {$section->schoolYear->name}).");
    }

    public function transfer(Request $request, Student $student, StudentEnrollment $enrollment): RedirectResponse
    {
        abort_unless((int) $enrollment->student_id === (int) $student->id, 404);

        $validated = $request->validate([
            'target_section_id' => ['required', 'exists:sections,id'],
            'transfer_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $targetSection = Section::query()->with(['schoolYear', 'gradeLevel'])->findOrFail($validated['target_section_id']);

        $this->enrollmentService->transferSection($enrollment, $targetSection, $validated['transfer_reason']);

        return redirect()
            ->route('admin.students.show', $student)
            ->with('status', "Transferred {$student->full_name} to {$targetSection->gradeLevel->name} – {$targetSection->name}. All prior academic records have been preserved.");
    }

    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_import_template.csv"',
        ];

        return response()->stream(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'student_number',
                'lrn',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'gender',
                'date_of_birth',
                'address',
                'guardian_name',
                'guardian_contact',
            ]);
            fputcsv($out, [
                '2026-0001',
                '109876543210',
                'Juan',
                'Dela',
                'Cruz',
                '',
                'Male',
                '2011-05-14',
                '123 Rizal St., City',
                'Maria Dela Cruz',
                '09171234567',
            ]);
            fclose($out);
        }, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $targetSection = ! empty($validated['section_id'])
            ? Section::query()->with(['schoolYear', 'gradeLevel'])->findOrFail($validated['section_id'])
            : null;

        $result = $this->importService->import($request->file('import_file'), $targetSection);

        $redirect = redirect()->route('admin.students.index')
            ->with('status', "Bulk import finished: {$result['imported']} imported, {$result['skipped']} skipped.");

        if (! empty($result['errors'])) {
            $redirect->with('import_errors', $result['errors']);
        }

        return $redirect;
    }
}
