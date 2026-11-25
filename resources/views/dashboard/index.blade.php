@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')
@section('subheader', $school->school_name . ($activeSchoolYear ? ' • Active SY ' . $activeSchoolYear->name : ' • No Active School Year'))

@section('content')
<div class="space-y-6">
    {{-- Role Hero Banner --}}
    <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-school-800 to-school-700 text-white p-6 shadow-md relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">{{ $user->role_labels }}</p>
                <h2 class="text-2xl font-bold mt-1">Welcome back, {{ $user->name }}</h2>
                <p class="text-xs text-school-100 mt-1">
                    @if($activeSchoolYear)
                        School Year <strong>{{ $activeSchoolYear->name }}</strong>
                        @if($currentPeriod)
                            &bull; Active Period: <strong>{{ $currentPeriod->name }} ({{ $currentPeriod->code }})</strong>
                            <span class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $currentPeriod->status === 'open' ? 'bg-emerald-500/25 text-emerald-200' : 'bg-rose-500/25 text-rose-200' }}">
                                {{ $currentPeriod->status }}
                            </span>
                        @endif
                    @else
                        Configure your active School Year to begin grading operations.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($user->isAdmin())
                    <a href="{{ route('admin.submissions.index', $currentPeriod ? ['grading_period_id' => $currentPeriod->id] : []) }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold shadow-sm transition">
                        Monitor Submissions
                    </a>
                    <a href="{{ route('admin.school-years.index') }}" class="px-4 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-semibold transition">
                        Manage Periods
                    </a>
                @elseif($user->isAdviser())
                    <a href="{{ route('adviser.sections.index') }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold shadow-sm transition">
                        My Advisory Sections
                    </a>
                    <a href="{{ route('teacher.classes.index') }}" class="px-4 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-semibold transition">
                        My Teaching Load
                    </a>
                @else
                    <a href="{{ route('teacher.classes.index') }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold shadow-sm transition">
                        Open My Gradebooks &rarr;
                    </a>
                @endif
            </div>
        </div>

        @if(isset($activePeriods) && $activePeriods->isNotEmpty())
            <div class="relative z-10 mt-5 pt-4 border-t border-white/10 flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-school-200 mr-1">Viewing Term:</span>
                @foreach($activePeriods as $p)
                    @php $isSelected = $currentPeriod && $currentPeriod->id === $p->id; @endphp
                    <a href="{{ route('dashboard', ['period_id' => $p->id]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold transition {{ $isSelected ? 'bg-white text-slate-900 shadow-sm' : 'bg-white/10 hover:bg-white/20 text-white' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $p->status === 'open' ? 'bg-emerald-400' : ($p->status === 'locked' ? 'bg-rose-400' : 'bg-slate-400') }}"></span>
                        {{ $p->code }}
                        <span class="text-[10px] opacity-75">({{ ucfirst($p->status) }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- School Bulletin / Active Memos for User's Role --}}
    @if(isset($announcements) && $announcements->isNotEmpty())
        <div class="school-card overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">School Bulletin &amp; Official Memos</h3>
                </div>
                @if($user->isAdmin())
                    <a href="{{ route('admin.announcements.index') }}" class="text-xs font-semibold text-school-600 hover:underline">Manage Bulletins &rarr;</a>
                @endif
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($announcements as $notice)
                    @php
                        $badgeStyle = match ($notice->category) {
                            'deadline' => 'bg-rose-100 text-rose-800',
                            'event' => 'bg-emerald-100 text-emerald-800',
                            'memo' => 'bg-school-100 text-school-800',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <div class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($notice->is_pinned)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900">PINNED</span>
                                @endif
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badgeStyle }}">
                                    {{ \App\Models\Announcement::categoryLabels()[$notice->category] ?? ucfirst($notice->category) }}
                                </span>
                                <span class="text-sm font-bold text-slate-900">{{ $notice->title }}</span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $notice->body }}</p>
                        </div>
                        <span class="text-[11px] text-slate-400 whitespace-nowrap shrink-0">{{ $notice->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Quick Launch Bar for Admin --}}
    @if($user->isAdmin())
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="{{ route('admin.students.index') }}" class="school-card p-3.5 hover:border-school-300 hover:shadow-md transition flex items-center gap-3 group">
                <span class="w-9 h-9 rounded-xl bg-school-50 text-school-700 flex items-center justify-center shrink-0 group-hover:bg-school-600 group-hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">Students &amp; Import</p>
                    <p class="text-[11px] text-slate-500 truncate">Enroll or transfer</p>
                </div>
            </a>
            <a href="{{ route('admin.assignments.index') }}" class="school-card p-3.5 hover:border-school-300 hover:shadow-md transition flex items-center gap-3 group">
                <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">Teacher Loads</p>
                    <p class="text-[11px] text-slate-500 truncate">Advisers &amp; subjects</p>
                </div>
            </a>
            <a href="{{ route('admin.grading-policies.index') }}" class="school-card p-3.5 hover:border-school-300 hover:shadow-md transition flex items-center gap-3 group">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">Grading Policies</p>
                    <p class="text-[11px] text-slate-500 truncate">Weights &amp; tables</p>
                </div>
            </a>
            <a href="{{ route('admin.backups.index') }}" class="school-card p-3.5 hover:border-school-300 hover:shadow-md transition flex items-center gap-3 group">
                <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0 group-hover:bg-slate-800 group-hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">Database Backups</p>
                    <p class="text-[11px] text-slate-500 truncate">Snapshot &amp; restore</p>
                </div>
            </a>
        </div>
    @endif

    {{-- ==================== ADMINISTRATOR DASHBOARD ==================== --}}
    @if($user->isAdmin() && $adminStats)
        @if($adminStats['unconfirmed_policies'] > 0)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-sm text-amber-900">
                    <p class="font-semibold">{{ $adminStats['unconfirmed_policies'] }} DepEd Policy Reference(s) Awaiting Administrator Confirmation</p>
                    <p class="text-xs text-amber-800 mt-0.5">Verify and confirm applicable DepEd Orders (MATATAG DO 010 s. 2024, DO 015 s. 2026, Strengthened SHS DO 017 s. 2026) in the Policy Registry.</p>
                </div>
                <a href="{{ route('admin.policies.index') }}" class="shrink-0 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">
                    Review Policy Registry
                </a>
            </div>
        @endif

        {{-- 6 KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="school-card p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Students</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $adminStats['students_count'] }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Active learners</p>
            </div>
            <div class="school-card p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Teachers</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $adminStats['teachers_count'] }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Active faculty</p>
            </div>
            <div class="school-card p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Sections</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $adminStats['sections_count'] }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">SY {{ $adminStats['active_sy'] }}</p>
            </div>
            <div class="school-card p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Subjects</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $adminStats['subjects_count'] }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $adminStats['curricula_count'] }} curricula</p>
            </div>
            <div class="school-card p-4 border-emerald-200/80 bg-emerald-50/30">
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Submitted</p>
                <p class="mt-2 text-2xl font-extrabold text-emerald-700">{{ $adminStats['submitted_grades_count'] }}</p>
                <p class="text-[11px] text-emerald-600/80 mt-0.5">Official classes</p>
            </div>
            <div class="school-card p-4 border-amber-200/80 bg-amber-50/30">
                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Pending</p>
                <p class="mt-2 text-2xl font-extrabold text-amber-700">{{ $adminStats['pending_grades_count'] }}</p>
                <p class="text-[11px] text-amber-600/80 mt-0.5">Awaiting submit</p>
            </div>
        </div>

        {{-- Submission Progress Visual Chart + Grading Period Status --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 school-card p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Grade Submission Completion</h3>
                        <p class="text-xs text-slate-500">
                            {{ $adminStats['current_period'] ? $adminStats['current_period']->name . ' (' . $adminStats['current_period']->code . ')' : 'No active grading period' }}
                            &bull; {{ $adminStats['assignments_count'] }} assigned class(es)
                        </p>
                    </div>
                    <a href="{{ route('admin.submissions.index') }}" class="text-xs font-semibold text-school-600 hover:underline">Full Tracker &rarr;</a>
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-8">
                    {{-- Pure SVG Donut Chart (works 100% offline) --}}
                    @php
                        $pct = $adminStats['completion_percent'];
                        $circ = 2 * 3.14159 * 42;
                        $offset = $circ * (1 - ($pct / 100));
                    @endphp
                    <div class="relative w-32 h-32 shrink-0">
                        <svg class="w-32 h-32 -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="#e2e8f0" stroke-width="10"/>
                            <circle cx="50" cy="50" r="42" fill="none" stroke="{{ $pct === 100 ? '#10b981' : '#1d4ed8' }}"
                                    stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-extrabold text-slate-900">{{ $pct }}%</span>
                            <span class="text-[10px] font-semibold uppercase text-slate-500">Complete</span>
                        </div>
                    </div>

                    <div class="flex-1 w-full space-y-3 text-xs">
                        @php $tot = max(1, $adminStats['assignments_count']); $bd = $adminStats['submission_breakdown']; @endphp
                        @foreach([
                            ['Approved', $bd['approved'], 'bg-emerald-500'],
                            ['Submitted (Pending Review)', $bd['submitted'], 'bg-school-600'],
                            ['Returned for Revision', $bd['returned'], 'bg-rose-500'],
                            ['Pending / Draft', $bd['pending'], 'bg-amber-400'],
                        ] as [$label, $count, $color])
                            <div>
                                <div class="flex justify-between font-medium text-slate-700 mb-1">
                                    <span>{{ $label }}</span>
                                    <span>{{ $count }}</span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $color }}" style="width: {{ round(($count / $tot) * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="school-card p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">Grading Period Status</h3>
                    <a href="{{ route('admin.school-years.index') }}" class="text-xs font-semibold text-school-600 hover:underline">Manage</a>
                </div>
                <div class="space-y-2.5">
                    @forelse($adminStats['periods'] as $p)
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $p->name }}</p>
                                <p class="text-[11px] text-slate-500 font-mono">{{ $p->code }}</p>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full font-semibold capitalize {{ $p->status === 'open' ? 'bg-emerald-100 text-emerald-800' : ($p->status === 'locked' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') }}">
                                {{ $p->status }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">No grading periods configured yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent Activity Feed --}}
        <div class="school-card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900">Recent Audit Activity ({{ $adminStats['audit_events_today'] }} today)</h3>
                <a href="{{ route('admin.audit-logs.index') }}" class="text-xs font-semibold text-school-600 hover:underline">View full log &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Timestamp</th>
                        <th class="px-6 py-3">Actor</th>
                        <th class="px-6 py-3">Event</th>
                        <th class="px-6 py-3">IP Address</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($recentLogs as $log)
                        <tr>
                            <td class="px-6 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-6 py-3 font-medium text-slate-900">{{ $log->actor?->name ?? 'System' }}</td>
                            <td class="px-6 py-3"><span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">{{ $log->event_type }}</span></td>
                            <td class="px-6 py-3 text-xs text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No activity logs recorded yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ==================== ADVISER DASHBOARD SECTION ==================== --}}
    @if($user->isAdviser() && $adviserCards->isNotEmpty())
        <div class="space-y-4">
            <h3 class="text-base font-bold text-slate-900">My Advisory Section Monitoring</h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($adviserCards as $card)
                    @php
                        $sec = $card['section'];
                        $m = $card['matrix'];
                    @endphp
                    <div class="school-card p-6 space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-school-700">SY {{ $sec->schoolYear->name }}</p>
                                <h4 class="text-lg font-bold text-slate-900">{{ $sec->gradeLevel->name }} – {{ $sec->name }}</h4>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $card['students_count'] }} Enrolled Learner(s)</p>
                            </div>
                            @if($m && $m['ready_for_report_cards'])
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Ready for Report Cards</span>
                            @elseif($m)
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">{{ $m['pending_subjects'] }} Pending Subject(s)</span>
                            @endif
                        </div>

                        @if($m)
                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                                    <span>Subject Submissions ({{ $currentPeriod?->code }})</span>
                                    <span>{{ $m['submitted_subjects'] }}/{{ $m['total_subjects'] }} ({{ $m['progress_percent'] }}%)</span>
                                </div>
                                <div class="w-full h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $m['progress_percent'] === 100 ? 'bg-emerald-500' : 'bg-school-600' }}" style="width: {{ $m['progress_percent'] }}%"></div>
                                </div>
                                <p class="text-xs text-slate-500 mt-2">
                                    Learners with incomplete grades: <strong class="{{ $m['students_with_incomplete_grades'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $m['students_with_incomplete_grades'] }}</strong>
                                </p>
                            </div>
                        @endif

                        <div class="pt-3 border-t border-slate-100 flex flex-wrap gap-2">
                            <a href="{{ route('adviser.sections.show', $sec) }}" class="px-3.5 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                                Open Collection Board
                            </a>
                            @if($currentPeriod && $m)
                                <a href="{{ route('adviser.report-cards.batch', ['section' => $sec, 'period_id' => $currentPeriod->id, 'draft' => $m['ready_for_report_cards'] ? 0 : 1]) }}" target="_blank"
                                   class="px-3.5 py-2 rounded-lg {{ $m['ready_for_report_cards'] ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-amber-100 hover:bg-amber-200 text-amber-900' }} text-xs font-semibold">
                                    {{ $m['ready_for_report_cards'] ? 'Generate Batch PDF' : 'Preview Draft PDF' }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ==================== SUBJECT TEACHER DASHBOARD SECTION ==================== --}}
    @if($teacherDashboard)
        <div class="space-y-4">
            <h3 class="text-base font-bold text-slate-900">My Subject Teaching Load &amp; Grade Submission Progress</h3>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="school-card p-4">
                    <p class="text-[11px] font-bold uppercase text-slate-500">Assigned Classes</p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $teacherDashboard['total_classes'] }}</p>
                    <p class="text-[11px] text-slate-400">Across {{ $teacherDashboard['unique_sections'] }} section(s)</p>
                </div>
                <div class="school-card p-4">
                    <p class="text-[11px] font-bold uppercase text-slate-500">Total Students</p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $teacherDashboard['total_students'] }}</p>
                    <p class="text-[11px] text-slate-400">Under your subjects</p>
                </div>
                <div class="school-card p-4 border-emerald-200/80">
                    <p class="text-[11px] font-bold uppercase text-emerald-700">Submitted</p>
                    <p class="mt-1 text-2xl font-extrabold text-emerald-700">{{ $teacherDashboard['submitted_classes'] }} / {{ $teacherDashboard['total_classes'] }}</p>
                    <p class="text-[11px] text-emerald-600/80">{{ $teacherDashboard['progress_percent'] }}% complete</p>
                </div>
                <div class="school-card p-4 border-amber-200/80">
                    <p class="text-[11px] font-bold uppercase text-amber-700">Missing Scores</p>
                    <p class="mt-1 text-2xl font-extrabold text-amber-700">{{ $teacherDashboard['total_missing_scores'] }}</p>
                    <p class="text-[11px] text-amber-600/80">Across active period</p>
                </div>
            </div>

            <div class="school-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Section &amp; Subject</th>
                            <th class="px-5 py-3 text-center">Students</th>
                            <th class="px-5 py-3 text-center">Assessments</th>
                            <th class="px-5 py-3 text-center">Missing Scores</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Gradebook</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($teacherDashboard['class_rows'] as $row)
                            @php $ta = $row['assignment']; @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $ta->subject->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $ta->section->gradeLevel->name }} – {{ $ta->section->name }}</p>
                                </td>
                                <td class="px-5 py-3 text-center text-slate-600">{{ $row['students_count'] }}</td>
                                <td class="px-5 py-3 text-center text-slate-600">{{ $row['assessments_count'] }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="font-semibold {{ $row['missing_scores'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $row['missing_scores'] }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize {{ $row['is_submitted'] ? 'bg-emerald-100 text-emerald-800' : ($row['status'] === 'returned' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $row['status'] }}
                                    </span>
                                    @if(! empty($row['prior_teacher_name']))
                                        <p class="text-[10px] text-slate-500 mt-0.5">By {{ $row['prior_teacher_name'] }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if($teacherDashboard['current_period'])
                                        <a href="{{ route('teacher.gradebook.show', [$ta, $teacherDashboard['current_period']]) }}"
                                           class="px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                                            Open Gradebook &rarr;
                                        </a>
                                    @else
                                        <a href="{{ route('teacher.classes.show', $ta) }}" class="text-xs font-semibold text-school-600 hover:underline">View Class</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No active subject classes assigned yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
