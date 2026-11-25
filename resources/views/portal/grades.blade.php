<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Parent &amp; Learner Grade Portal — {{ $school->school_name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-[#f4f6fb] text-slate-800 antialiased min-h-screen flex flex-col">
<div class="h-1 bg-gradient-to-r from-school-800 via-amber-500 to-school-700"></div>

<header class="bg-white/95 backdrop-blur-md border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0">
            @if($school->logo_path)
                <img src="{{ asset('storage/' . $school->logo_path) }}" alt="Logo" class="h-9 w-9 rounded-xl object-cover border border-slate-200 p-0.5">
            @else
                <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-school-700 to-school-900 text-amber-400 flex items-center justify-center font-extrabold text-sm shrink-0">SG</div>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-extrabold text-slate-900 truncate">{{ $school->school_name }}</p>
                <p class="text-[11px] text-slate-500 truncate">Parent &amp; Learner Official Grade Inquiry (Read-Only)</p>
            </div>
        </a>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-slate-600 hover:text-school-700 px-3 py-1.5">&larr; Home</a>
            <a href="{{ route('login') }}" class="px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold">Faculty Sign In</a>
        </div>
    </div>
</header>

<main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-8 space-y-6">
    @if(! ($school->parent_portal_enabled ?? true))
        <div class="school-card p-10 text-center space-y-3">
            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900">Portal Unavailable</span>
            <h1 class="text-xl font-extrabold text-slate-900">Parent &amp; Learner Grade Inquiry Is Currently Closed</h1>
            <p class="text-sm text-slate-600 max-w-md mx-auto">The School Administrator has temporarily disabled public grade lookups. Please contact your Class Adviser or School Registrar for official SF9 report card distribution.</p>
        </div>
    @else
        {{-- 3-Factor Learner Verification Card --}}
        <div class="school-card p-6 sm:p-8">
            <div class="mb-5">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-school-50 text-school-700 border border-school-100">
                    3-Factor Learner Verification
                </span>
                <h1 class="text-xl font-extrabold text-slate-900 mt-1.5">View Official Learner Grades</h1>
                <p class="text-xs text-slate-500 mt-0.5">Enter the learner's Student ID Number, 12-digit DepEd LRN, and Date of Birth. Only finalized, officially submitted grades are displayed.</p>
            </div>

            @if($lookupError)
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs font-semibold text-rose-800">
                    {{ $lookupError }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs text-rose-800">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.grades.lookup') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Student ID Number <span class="text-rose-600">*</span></label>
                    <input type="text" name="student_number" required value="{{ old('student_number', request('student_number')) }}" placeholder="e.g. 2026-0001" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">12-Digit DepEd LRN <span class="text-rose-600">*</span></label>
                    <input type="text" name="lrn" required maxlength="12" value="{{ old('lrn', request('lrn')) }}" placeholder="100000000001" class="w-full rounded-lg border-slate-300 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Date of Birth <span class="text-rose-600">*</span></label>
                    <input type="date" name="date_of_birth" required value="{{ old('date_of_birth', request('date_of_birth')) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <button type="submit" class="w-full school-btn-primary">
                        View Official Grades &rarr;
                    </button>
                </div>
            </form>
        </div>

        {{-- Verified Learner Official Grades Output --}}
        @if($student && $selectedEnrollment && $summary)
            <div class="space-y-5">
                <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-school-900 to-school-700 text-white p-6 shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-amber-300">Verified Learner Official Record</p>
                        <h2 class="text-2xl font-extrabold mt-0.5">{{ $student->formal_name }}</h2>
                        <p class="text-xs text-school-100 mt-1">
                            {{ $selectedEnrollment->gradeLevel->name }} &ndash; {{ $selectedEnrollment->section->name }}
                            &bull; SY {{ $selectedEnrollment->schoolYear->name }}
                            &bull; Adviser: {{ $selectedEnrollment->section->adviser?->full_name ?? 'TBA' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @if($honorDistinction)
                            <span class="px-3 py-1.5 rounded-xl bg-amber-400 text-slate-950 text-xs font-extrabold shadow-sm">
                                {{ $honorDistinction }}
                            </span>
                        @endif
                        <div class="px-4 py-2 rounded-xl bg-white/10 border border-white/15 text-center">
                            <p class="text-[10px] font-semibold uppercase text-school-200">General Avg</p>
                            <p class="text-xl font-extrabold">
                                {{ $summary['general_average'] !== null ? number_format($summary['general_average'], 0) : ($summary['is_descriptive_only'] ? 'Descriptive' : 'In Progress') }}
                            </p>
                        </div>
                    </div>
                </div>

                @if($enrollments->count() > 1)
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold text-slate-500">Switch School Year:</span>
                        @foreach($enrollments as $enrOption)
                            <form method="POST" action="{{ route('portal.grades.lookup') }}" class="inline">
                                @csrf
                                <input type="hidden" name="student_number" value="{{ $student->student_number }}">
                                <input type="hidden" name="lrn" value="{{ $student->lrn }}">
                                <input type="hidden" name="date_of_birth" value="{{ $student->date_of_birth->format('Y-m-d') }}">
                                <input type="hidden" name="enrollment_id" value="{{ $enrOption->id }}">
                                <button type="submit" class="px-3 py-1 rounded-lg font-semibold {{ $enrOption->id === $selectedEnrollment->id ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                                    SY {{ $enrOption->schoolYear->name }} ({{ $enrOption->gradeLevel->code }})
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif

                <div class="school-card overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Official Subject Grades ({{ $selectedEnrollment->curriculum->name }})</h3>
                            <p class="text-xs text-slate-500">Unsubmitted subjects appear as Pending until officially finalized by the subject teacher.</p>
                        </div>
                        <button type="button" onclick="window.print()" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-100 text-xs font-semibold text-slate-700">
                            Print Copy
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Learning Area</th>
                                @foreach($summary['periods'] as $p)
                                    <th class="px-4 py-3 text-center">{{ $p->code }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-center bg-school-50 text-school-900">Final Grade</th>
                                <th class="px-5 py-3">Remarks</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($summary['subject_rows'] as $row)
                                <tr>
                                    <td class="px-5 py-3 font-semibold text-slate-900">{{ $row['subject']->name }}</td>
                                    @foreach($summary['periods'] as $p)
                                        @php $val = $row['period_grades'][$p->id] ?? null; @endphp
                                        <td class="px-4 py-3 text-center font-semibold">
                                            @if($val !== null)
                                                <span class="{{ is_numeric($val) && (float) $val < 75 ? 'text-rose-700' : 'text-slate-900' }}">{{ $val }}</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-[11px]">Pending</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-center font-extrabold bg-school-50/40 {{ ($row['final_grade'] ?? 100) < 75 ? 'text-rose-700' : 'text-school-800' }}">
                                        {{ $row['final_grade'] !== null ? number_format($row['final_grade'], 0) : '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-xs text-slate-600">{{ $row['remarks'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($school->attendance_enabled)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="school-card p-4">
                            <p class="text-[10px] font-bold uppercase text-slate-500">Total School Days</p>
                            <p class="text-xl font-extrabold text-slate-900 mt-1">{{ $summary['attendance_totals']['school_days'] }}</p>
                        </div>
                        <div class="school-card p-4">
                            <p class="text-[10px] font-bold uppercase text-emerald-700">Days Present</p>
                            <p class="text-xl font-extrabold text-emerald-700 mt-1">{{ $summary['attendance_totals']['days_present'] }}</p>
                        </div>
                        <div class="school-card p-4">
                            <p class="text-[10px] font-bold uppercase text-rose-700">Days Absent</p>
                            <p class="text-xl font-extrabold text-rose-700 mt-1">{{ $summary['attendance_totals']['days_absent'] }}</p>
                        </div>
                        <div class="school-card p-4">
                            <p class="text-[10px] font-bold uppercase text-amber-700">Days Tardy</p>
                            <p class="text-xl font-extrabold text-amber-700 mt-1">{{ $summary['attendance_totals']['days_tardy'] }}</p>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif
</main>

<footer class="bg-white border-t border-slate-200 py-5 text-center text-xs text-slate-400">
    &copy; {{ date('Y') }} {{ $school->school_name }} &bull; Official Read-Only Parent &amp; Learner Grade Portal
</footer>
</body>
</html>
