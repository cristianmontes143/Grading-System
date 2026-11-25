<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        $students = Student::query()
            ->with(['currentEnrollment.section.gradeLevel'])
            ->where(function ($query) use ($q): void {
                $query->where('first_name', 'ilike', "%{$q}%")
                    ->orWhere('last_name', 'ilike', "%{$q}%")
                    ->orWhere('student_number', 'ilike', "%{$q}%")
                    ->orWhere('lrn', 'ilike', "%{$q}%");
            })
            ->orderBy('last_name')
            ->limit(5)
            ->get();

        foreach ($students as $student) {
            $secLabel = $student->currentEnrollment
                ? $student->currentEnrollment->section->gradeLevel->name . ' – ' . $student->currentEnrollment->section->name
                : 'Unenrolled';
            $results[] = [
                'label' => $student->formal_name,
                'sublabel' => trim("{$student->student_number}" . ($student->lrn ? " • LRN {$student->lrn}" : '') . " • {$secLabel}"),
                'group' => 'Student',
                'url' => route('admin.students.show', $student),
            ];
        }

        $teachers = Teacher::query()
            ->where(function ($query) use ($q): void {
                $query->where('first_name', 'ilike', "%{$q}%")
                    ->orWhere('last_name', 'ilike', "%{$q}%")
                    ->orWhere('employee_number', 'ilike', "%{$q}%")
                    ->orWhere('specialization', 'ilike', "%{$q}%");
            })
            ->orderBy('last_name')
            ->limit(4)
            ->get();

        foreach ($teachers as $teacher) {
            $results[] = [
                'label' => $teacher->full_name,
                'sublabel' => trim("{$teacher->employee_number}" . ($teacher->specialization ? " • {$teacher->specialization}" : '')),
                'group' => 'Teacher',
                'url' => route('admin.teachers.index', ['search' => $teacher->employee_number]),
            ];
        }

        $sections = Section::query()
            ->with(['gradeLevel', 'schoolYear'])
            ->where('is_active', true)
            ->where(function ($query) use ($q): void {
                $query->where('name', 'ilike', "%{$q}%")
                    ->orWhereHas('gradeLevel', fn ($gl) => $gl->where('name', 'ilike', "%{$q}%")->orWhere('code', 'ilike', "%{$q}%"));
            })
            ->orderByDesc('school_year_id')
            ->orderBy('name')
            ->limit(4)
            ->get();

        foreach ($sections as $section) {
            $results[] = [
                'label' => "{$section->gradeLevel->name} – {$section->name}",
                'sublabel' => "SY {$section->schoolYear->name}",
                'group' => 'Section',
                'url' => route('adviser.sections.show', $section),
            ];
        }

        return response()->json(['results' => $results]);
    }
}
