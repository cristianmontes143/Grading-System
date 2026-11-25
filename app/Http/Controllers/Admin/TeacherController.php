<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        $activeSy = SchoolYear::current();

        $query = Teacher::query()
            ->with(['user.roles'])
            ->withCount([
                'advisorySections as advisory_sections_count' => fn ($q) => $activeSy ? $q->where('school_year_id', $activeSy->id) : $q,
                'activeAssignments as active_assignments_count' => fn ($q) => $activeSy ? $q->where('school_year_id', $activeSy->id) : $q,
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('employee_number', 'ilike', "%{$search}%")
                    ->orWhere('specialization', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        $teachers = $query->paginate(20)->withQueryString();
        $teacherRoles = Role::query()
            ->whereIn('name', [Role::ADVISER, Role::SUBJECT_TEACHER])
            ->orderBy('id')
            ->get();

        return view('admin.teachers.index', [
            'teachers' => $teachers,
            'teacherRoles' => $teacherRoles,
            'activeSy' => $activeSy,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $activeSy = SchoolYear::current();
        $query = Teacher::query()
            ->with([
                'user.roles',
                'advisorySections' => fn ($q) => $activeSy ? $q->where('school_year_id', $activeSy->id)->with('gradeLevel') : $q->with('gradeLevel'),
                'activeAssignments' => fn ($q) => $activeSy ? $q->where('school_year_id', $activeSy->id)->with(['section.gradeLevel', 'subject']) : $q->with(['section.gradeLevel', 'subject']),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('employee_number', 'ilike', "%{$search}%")
                    ->orWhere('specialization', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        $teachers = $query->get();
        $school = SchoolSetting::current();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Faculty & Teaching Loads');

        $sheet->fromArray([
            [$school->school_name . ' — Faculty Directory & Active Teaching Loads' . ($activeSy ? " (SY {$activeSy->name})" : '')],
            ['Generated: ' . now()->format('F d, Y h:i A') . ' | Total Faculty: ' . $teachers->count()],
            [],
            ['#', 'Employee No.', 'Full Name', 'Email', 'Role(s)', 'Specialization', 'Advisory Section(s)', 'Active Subject Loads', 'Status'],
        ], null, 'A1');

        $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A4:I4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:I4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');

        $rowNum = 5;
        foreach ($teachers as $idx => $t) {
            $advisory = $t->advisorySections->map(fn ($s) => $s->gradeLevel->code . ' ' . $s->name)->implode(', ') ?: 'None';
            $loads = $t->activeAssignments->map(fn ($a) => "{$a->subject->code} ({$a->section->gradeLevel->code} {$a->section->name})")->implode('; ') ?: 'None';

            $sheet->fromArray([[
                $idx + 1,
                $t->employee_number,
                $t->full_name,
                $t->user?->email ?? '',
                $t->user?->role_labels ?? '',
                $t->specialization ?? '',
                $advisory,
                $loads,
                $t->is_active ? 'Active' : 'Inactive',
            ]], null, "A{$rowNum}");
            $rowNum++;
        }

        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $filename = 'faculty_teaching_loads_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', 'unique:teachers,employee_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role_name' => ['required', Rule::in([Role::ADVISER, Role::SUBJECT_TEACHER])],
        ]);

        $teacher = DB::transaction(function () use ($validated): Teacher {
            $fullName = trim(implode(' ', array_filter([
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
                $validated['suffix'] ?? null,
            ])));

            $user = User::query()->create([
                'name' => $fullName,
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $roleIds = Role::query()
                ->whereIn('name', $validated['role_name'] === Role::ADVISER
                    ? [Role::ADVISER, Role::SUBJECT_TEACHER]
                    : [Role::SUBJECT_TEACHER]
                )
                ->pluck('id')
                ->all();

            $user->roles()->sync($roleIds);

            return Teacher::query()->create([
                'user_id' => $user->id,
                'employee_number' => strtoupper(trim($validated['employee_number'])),
                'first_name' => trim($validated['first_name']),
                'middle_name' => isset($validated['middle_name']) ? trim($validated['middle_name']) : null,
                'last_name' => trim($validated['last_name']),
                'suffix' => isset($validated['suffix']) ? trim($validated['suffix']) : null,
                'gender' => $validated['gender'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
                'is_active' => true,
            ]);
        });

        $this->auditLog->record(
            eventType: 'teacher.created',
            auditable: $teacher,
            newValues: [
                'employee_number' => $teacher->employee_number,
                'name' => $teacher->full_name,
                'email' => $teacher->user->email,
                'role' => $validated['role_name'],
            ],
            request: $request,
            context: ['teacher_id' => $teacher->id]
        );

        return redirect()
            ->route('admin.teachers.index')
            ->with('status', "Teacher {$teacher->full_name} account created successfully.");
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('teachers', 'employee_number')->ignore($teacher->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher->user_id)],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'role_name' => ['required', Rule::in([Role::ADVISER, Role::SUBJECT_TEACHER])],
        ]);

        $oldValues = [
            'employee_number' => $teacher->employee_number,
            'name' => $teacher->full_name,
            'email' => $teacher->user->email,
        ];

        DB::transaction(function () use ($teacher, $validated): void {
            $teacher->update([
                'employee_number' => strtoupper(trim($validated['employee_number'])),
                'first_name' => trim($validated['first_name']),
                'middle_name' => isset($validated['middle_name']) ? trim($validated['middle_name']) : null,
                'last_name' => trim($validated['last_name']),
                'suffix' => isset($validated['suffix']) ? trim($validated['suffix']) : null,
                'gender' => $validated['gender'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
            ]);

            $userData = [
                'name' => $teacher->full_name,
                'email' => strtolower(trim($validated['email'])),
            ];
            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            $teacher->user->update($userData);

            $roleIds = Role::query()
                ->whereIn('name', $validated['role_name'] === Role::ADVISER
                    ? [Role::ADVISER, Role::SUBJECT_TEACHER]
                    : [Role::SUBJECT_TEACHER]
                )
                ->pluck('id')
                ->all();

            // Preserve admin role if user also happens to be admin
            if ($teacher->user->isAdmin()) {
                $adminId = Role::query()->where('name', Role::ADMIN)->value('id');
                if ($adminId) {
                    $roleIds[] = $adminId;
                }
            }

            $teacher->user->roles()->sync(array_unique($roleIds));
        });

        $this->auditLog->record(
            eventType: 'teacher.updated',
            auditable: $teacher,
            oldValues: $oldValues,
            newValues: [
                'employee_number' => $teacher->employee_number,
                'name' => $teacher->full_name,
                'email' => $teacher->user->fresh()->email,
                'role' => $validated['role_name'],
            ],
            request: $request,
            context: ['teacher_id' => $teacher->id]
        );

        return redirect()
            ->route('admin.teachers.index')
            ->with('status', "Teacher {$teacher->full_name} updated.");
    }

    public function toggle(Request $request, Teacher $teacher): RedirectResponse
    {
        $newState = ! $teacher->is_active;

        DB::transaction(function () use ($teacher, $newState): void {
            $teacher->update(['is_active' => $newState]);
            $teacher->user->update(['is_active' => $newState]);
        });

        $this->auditLog->record(
            eventType: 'teacher.status_toggled',
            auditable: $teacher,
            oldValues: ['is_active' => ! $newState],
            newValues: ['is_active' => $newState],
            request: $request,
            context: ['teacher_id' => $teacher->id]
        );

        return redirect()
            ->route('admin.teachers.index')
            ->with('status', "Teacher {$teacher->full_name} is now " . ($newState ? 'active' : 'deactivated') . '.');
    }
}
