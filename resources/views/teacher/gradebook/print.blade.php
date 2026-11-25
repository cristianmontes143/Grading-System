<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Subject Grade Sheet — {{ $assignment->subject->name }} ({{ $period->code }})</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-slate-100 p-6 font-sans text-slate-900">
<div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-sm print:shadow-none print:p-0">
    <div class="no-print flex justify-between items-center mb-6 pb-4 border-b border-slate-200">
        <a href="{{ route('teacher.gradebook.show', [$assignment, $period]) }}" class="text-xs font-semibold text-slate-600">&larr; Back to Gradebook</a>
        <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg bg-school-600 text-white text-xs font-semibold">
            Print Grade Sheet
        </button>
    </div>

    <div class="text-center border-b border-slate-300 pb-4 mb-5">
        <p class="text-xs uppercase tracking-wider text-slate-600">{{ $school->region ?? 'Department of Education' }} {{ $school->division ? '• ' . $school->division : '' }}</p>
        <h1 class="text-xl font-bold text-slate-900 mt-0.5">{{ $school->school_name }}</h1>
        <p class="text-sm font-semibold text-slate-700 mt-1">SUBJECT GRADE SHEET — SY {{ $assignment->schoolYear->name }} ({{ $period->name }})</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs mb-5">
        <div><span class="text-slate-500">Section:</span> <strong>{{ $assignment->section->gradeLevel->name }} – {{ $assignment->section->name }}</strong></div>
        <div><span class="text-slate-500">Subject:</span> <strong>{{ $assignment->subject->code }} – {{ $assignment->subject->name }}</strong></div>
        <div><span class="text-slate-500">Subject Teacher:</span> <strong>{{ $assignment->teacher->full_name }}</strong></div>
        <div><span class="text-slate-500">Adviser:</span> <strong>{{ $assignment->section->adviser?->full_name ?? 'Unassigned' }}</strong></div>
    </div>

    <table class="w-full border-collapse border border-slate-300 text-xs">
        <thead class="bg-slate-100">
        <tr>
            <th class="border border-slate-300 px-2 py-1.5 text-left">#</th>
            <th class="border border-slate-300 px-2 py-1.5 text-left">Student ID</th>
            <th class="border border-slate-300 px-2 py-1.5 text-left">Learner Name</th>
            @foreach($assessments as $a)
                <th class="border border-slate-300 px-2 py-1.5 text-center">{{ $a->component->code }}: {{ $a->title }}<br>({{ $a->max_score }})</th>
            @endforeach
            <th class="border border-slate-300 px-2 py-1.5 text-center">Initial</th>
            <th class="border border-slate-300 px-2 py-1.5 text-center">Grade</th>
            <th class="border border-slate-300 px-2 py-1.5 text-left">Remarks</th>
        </tr>
        </thead>
        <tbody>
        @foreach($enrollments as $idx => $enr)
            @php $qg = $gradesByEnrollment->get($enr->id); @endphp
            <tr>
                <td class="border border-slate-300 px-2 py-1">{{ $idx + 1 }}</td>
                <td class="border border-slate-300 px-2 py-1 font-mono">{{ $enr->student->student_number }}</td>
                <td class="border border-slate-300 px-2 py-1 font-medium">{{ $enr->student->formal_name }}</td>
                @foreach($assessments as $a)
                    @php $sc = $scoresByKey->get($enr->id . ':' . $a->id); @endphp
                    <td class="border border-slate-300 px-2 py-1 text-center">
                        @if(! $sc || $sc->status === 'missing') M
                        @elseif($sc->status === 'excused') EX
                        @else {{ $sc->raw_score }}
                        @endif
                    </td>
                @endforeach
                <td class="border border-slate-300 px-2 py-1 text-center">{{ $qg?->initial_grade !== null ? number_format($qg->initial_grade, 2) : '—' }}</td>
                <td class="border border-slate-300 px-2 py-1 text-center font-bold">{{ $qg?->display_grade ?? '—' }}</td>
                <td class="border border-slate-300 px-2 py-1">{{ $qg?->remarks ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="grid grid-cols-2 gap-12 mt-12 text-xs text-center">
        <div>
            <div class="border-t border-slate-400 pt-1 font-semibold">{{ $assignment->teacher->full_name }}</div>
            <p class="text-slate-500">Subject Teacher</p>
        </div>
        <div>
            <div class="border-t border-slate-400 pt-1 font-semibold">{{ $school->principal_name ?? 'School Head' }}</div>
            <p class="text-slate-500">{{ $school->principal_title }}</p>
        </div>
    </div>
</div>
</body>
</html>
