<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ \App\Models\SchoolSetting::current()->school_name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
@php
    $school = \App\Models\SchoolSetting::current();
    $currentUser = auth()->user();

    $quickLinks = [
        ['label' => 'Dashboard', 'group' => 'Overview', 'url' => route('dashboard')],
        ['label' => 'DepEd Grade Calculator Simulator', 'group' => 'Tools', 'url' => route('tools.grade-simulator')],
        ['label' => 'Notifications Center', 'group' => 'Account', 'url' => route('notifications.index')],
        ['label' => 'Profile & Password', 'group' => 'Account', 'url' => route('profile.edit')],
        ['label' => 'Public School Portal', 'group' => 'Portal', 'url' => route('home')],
    ];
    if ($currentUser->isAdmin()) {
        $quickLinks = array_merge($quickLinks, [
            ['label' => 'School Years & Grading Periods', 'group' => 'Academic', 'url' => route('admin.school-years.index')],
            ['label' => 'DepEd Policy Registry', 'group' => 'Academic', 'url' => route('admin.policies.index')],
            ['label' => 'Curricula & MATATAG', 'group' => 'Academic', 'url' => route('admin.curricula.index')],
            ['label' => 'Grade Levels (K–12)', 'group' => 'Academic', 'url' => route('admin.grade-levels.index')],
            ['label' => 'Subjects / Learning Areas', 'group' => 'Academic', 'url' => route('admin.subjects.index')],
            ['label' => 'Sections', 'group' => 'Academic', 'url' => route('admin.sections.index')],
            ['label' => 'Teachers', 'group' => 'Faculty & Students', 'url' => route('admin.teachers.index')],
            ['label' => 'Teacher Assignments', 'group' => 'Faculty & Students', 'url' => route('admin.assignments.index')],
            ['label' => 'Students & Enrollment', 'group' => 'Faculty & Students', 'url' => route('admin.students.index')],
            ['label' => 'Batch Promotion & Rollover', 'group' => 'Faculty & Students', 'url' => route('admin.promotions.index')],
            ['label' => 'Remedial Classes & Recomputed Grades', 'group' => 'Faculty & Students', 'url' => route('admin.remedials.index')],
            ['label' => 'Registrar Official Certificates Hub', 'group' => 'Faculty & Students', 'url' => route('admin.certificates.index')],
            ['label' => 'Learner Interventions Tracker', 'group' => 'Faculty & Students', 'url' => route('admin.interventions.index')],
            ['label' => 'Grading Configurations', 'group' => 'Grading', 'url' => route('admin.grading-policies.index')],
            ['label' => 'Transmutation Tables', 'group' => 'Grading', 'url' => route('admin.transmutation-tables.index')],
            ['label' => 'Grade Submissions Monitor', 'group' => 'Grading', 'url' => route('admin.submissions.index')],
            ['label' => 'Academic Analytics & Honor Roll', 'group' => 'Grading', 'url' => route('admin.analytics.index')],
            ['label' => 'DepEd MPS & SARDO Attendance Monitor', 'group' => 'Grading', 'url' => route('admin.mps-sardo.index')],
            ['label' => 'Report Card Templates', 'group' => 'Grading', 'url' => route('admin.report-card-templates.index')],
            ['label' => 'School Settings', 'group' => 'Administration', 'url' => route('admin.settings.edit')],
            ['label' => 'School-Year Readiness Checklist', 'group' => 'Administration', 'url' => route('admin.readiness.index')],
            ['label' => 'School Announcements & Memos', 'group' => 'Administration', 'url' => route('admin.announcements.index')],
            ['label' => 'Roles & Permissions', 'group' => 'Administration', 'url' => route('admin.roles.index')],
            ['label' => 'Activity & Audit Logs', 'group' => 'Administration', 'url' => route('admin.audit-logs.index')],
            ['label' => 'Database Backups', 'group' => 'Administration', 'url' => route('admin.backups.index')],
        ]);
    }
    if ($currentUser->isAdviser()) {
        $quickLinks[] = ['label' => 'My Advisory Sections', 'group' => 'Advisory', 'url' => route('adviser.sections.index')];
    }
    if ($currentUser->hasAnyRole(['adviser', 'subject_teacher'])) {
        $quickLinks[] = ['label' => 'My Teaching Load & Gradebooks', 'group' => 'Teaching', 'url' => route('teacher.classes.index')];
    }
@endphp

<body class="h-full font-sans antialiased text-slate-800"
      x-data="{
          sidebarOpen: false,
          cmdOpen: false,
          cmdQuery: '',
          cmdLinks: @js($quickLinks),
          liveResults: [],
          liveLoading: false,
          liveTimer: null,
          isAdmin: {{ $currentUser->isAdmin() ? 'true' : 'false' }},
          searchUrl: '{{ $currentUser->isAdmin() ? route('admin.quick-search') : '' }}',
          get filteredLinks() {
              const q = this.cmdQuery.trim().toLowerCase();
              if (!q) return this.cmdLinks;
              return this.cmdLinks.filter(item =>
                  item.label.toLowerCase().includes(q) || item.group.toLowerCase().includes(q)
              );
          },
          onCmdInput() {
              if (!this.isAdmin || !this.searchUrl) return;
              clearTimeout(this.liveTimer);
              const q = this.cmdQuery.trim();
              if (q.length < 2) {
                  this.liveResults = [];
                  this.liveLoading = false;
                  return;
              }
              this.liveLoading = true;
              this.liveTimer = setTimeout(() => {
                  fetch(this.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                      .then(r => r.ok ? r.json() : { results: [] })
                      .then(data => { this.liveResults = data.results || []; })
                      .catch(() => { this.liveResults = []; })
                      .finally(() => { this.liveLoading = false; });
              }, 220);
          },
          openCmd() {
              this.cmdOpen = true;
              this.cmdQuery = '';
              this.liveResults = [];
              this.$nextTick(() => this.$refs.cmdInput && this.$refs.cmdInput.focus());
          },
          goFirstMatch() {
              if (this.liveResults.length > 0) {
                  window.location.href = this.liveResults[0].url;
              } else if (this.filteredLinks.length > 0) {
                  window.location.href = this.filteredLinks[0].url;
              }
          }
      }"
      @keydown.window.ctrl.k.prevent="openCmd()"
      @keydown.window.meta.k.prevent="openCmd()"
      @keydown.window.escape="cmdOpen = false">

<div class="min-h-full flex">
    {{-- Mobile Backdrop --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-slate-950 via-slate-900 to-school-900 text-slate-100 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto border-r border-slate-800/80">
        <div class="h-1 bg-gradient-to-r from-amber-500 via-school-500 to-amber-400"></div>
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-800/80">
            @if($school->logo_path)
                <img src="{{ asset('storage/' . $school->logo_path) }}" alt="School Logo" class="h-9 w-9 rounded-lg object-cover bg-white p-0.5 shadow">
            @else
                <div class="h-9 w-9 rounded-lg bg-amber-500 text-slate-950 flex items-center justify-center font-extrabold text-sm shrink-0 shadow">
                    SG
                </div>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-bold text-white truncate">{{ $school->school_name }}</p>
                <p class="text-[11px] text-amber-300/90 font-medium truncate">DepEd MATATAG Grading</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Overview</p>
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>
            </div>

            @if($currentUser->isAdmin())
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Academic &amp; Curriculum</p>
                    <div class="space-y-1">
                        <a href="{{ route('admin.school-years.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.school-years.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            School Years &amp; Periods
                        </a>
                        <a href="{{ route('admin.policies.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.policies.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            DepEd Policy Registry
                        </a>
                        <a href="{{ route('admin.curricula.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.curricula.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            Curricula &amp; MATATAG
                        </a>
                        <a href="{{ route('admin.grade-levels.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.grade-levels.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Grade Levels (K–12)
                        </a>
                        <a href="{{ route('admin.subjects.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.subjects.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Subjects / Learning Areas
                        </a>
                        <a href="{{ route('admin.sections.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.sections.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Sections
                        </a>
                    </div>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Faculty &amp; Students</p>
                    <div class="space-y-1">
                        <a href="{{ route('admin.teachers.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.teachers.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Teachers
                        </a>
                        <a href="{{ route('admin.assignments.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.assignments.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            Teacher Assignments
                        </a>
                        <a href="{{ route('admin.students.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.students.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            </svg>
                            Students &amp; Enrollment
                        </a>
                        <a href="{{ route('admin.promotions.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.promotions.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                            Promotion &amp; Rollover
                        </a>
                        <a href="{{ route('admin.remedials.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.remedials.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Remedial Classes
                        </a>
                        <a href="{{ route('admin.certificates.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.certificates.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Registrar Certificates
                        </a>
                        <a href="{{ route('admin.interventions.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.interventions.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            Learner Interventions
                        </a>
                    </div>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Grading Policies</p>
                    <div class="space-y-1">
                        <a href="{{ route('admin.grading-policies.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.grading-policies.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            Grading Configurations
                        </a>
                        <a href="{{ route('admin.transmutation-tables.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.transmutation-tables.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Transmutation Tables
                        </a>
                        <a href="{{ route('admin.submissions.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.submissions.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Grade Submissions
                        </a>
                        <a href="{{ route('admin.analytics.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.analytics.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Analytics &amp; Honor Roll
                        </a>
                        <a href="{{ route('admin.mps-sardo.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.mps-sardo.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                            MPS &amp; SARDO Monitor
                        </a>
                        <a href="{{ route('admin.report-card-templates.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.report-card-templates.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            Report Card Templates
                        </a>
                    </div>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Administration</p>
                    <div class="space-y-1">
                        <a href="{{ route('admin.settings.edit') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.settings.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            School Settings
                        </a>
                        <a href="{{ route('admin.readiness.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.readiness.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            System Readiness
                        </a>
                        <a href="{{ route('admin.announcements.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.announcements.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                            </svg>
                            Announcements &amp; Memos
                        </a>
                        <a href="{{ route('admin.roles.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.roles.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Roles &amp; Permissions
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.audit-logs.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Activity &amp; Audit Logs
                        </a>
                        <a href="{{ route('admin.backups.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.backups.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                            </svg>
                            Database Backups
                        </a>
                    </div>
                </div>
            @endif

            @if($currentUser->isAdviser())
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Advisory</p>
                    <a href="{{ route('adviser.sections.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('adviser.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                        </svg>
                        My Advisory Sections
                    </a>
                </div>
            @endif

            @if($currentUser->hasAnyRole(['adviser', 'subject_teacher']))
                <div>
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Teaching</p>
                    <a href="{{ route('teacher.classes.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('teacher.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" />
                        </svg>
                        My Teaching Load
                    </a>
                </div>
            @endif

            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Tools &amp; Account</p>
                <div class="space-y-1">
                    <a href="{{ route('tools.grade-simulator') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('tools.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Grade Simulator
                    </a>
                    <a href="{{ route('profile.edit') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('profile.*') ? 'bg-school-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profile &amp; Password
                    </a>
                </div>
            </div>
        </nav>

        <div class="p-4 border-t border-slate-800 space-y-2.5">
            <div class="mb-2">
                <p class="text-sm font-medium text-white truncate">{{ $currentUser->name }}</p>
                <p class="text-xs text-slate-400 truncate">{{ $currentUser->role_labels }}</p>
            </div>
            <a href="{{ route('home') }}"
               class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800/70 text-slate-300 hover:bg-slate-800 hover:text-white transition">
                View Public Portal
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-slate-800 text-slate-200 hover:bg-rose-600 hover:text-white transition">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30">
            <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button type="button" @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none"
                            aria-label="Toggle Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">@yield('header', 'Dashboard')</h1>
                        @hasSection('subheader')
                            <p class="text-xs text-slate-500">@yield('subheader')</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Quick Jump Command Trigger --}}
                    <button type="button" @click="openCmd()"
                            class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs text-slate-500 transition">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span>Quick Jump...</span>
                        <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-[10px] font-mono text-slate-500">Ctrl K</kbd>
                    </button>

                    {{-- Notifications Bell Dropdown --}}
                    @php
                        $unreadCount = $currentUser->unreadNotifications()->count();
                        $recentNotifs = $currentUser->notifications()->latest()->limit(6)->get();
                    @endphp
                    <div class="relative" x-data="{ openNotif: false }">
                        <button type="button" @click="openNotif = !openNotif"
                                class="relative p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none"
                                aria-label="Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadCount > 0)
                                <span class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-rose-600 rounded-full">
                                    {{ $unreadCount }}
                                </span>
                            @endif
                        </button>
                        <div x-show="openNotif" x-cloak @click.away="openNotif = false"
                             class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden">
                            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800">Notifications</span>
                                @if($unreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="text-[11px] font-semibold text-school-600 hover:underline">Mark all read</button>
                                    </form>
                                @endif
                            </div>
                            <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                                @forelse($recentNotifs as $notif)
                                    <a href="{{ $notif->data['url'] ?? '#' }}"
                                       class="block px-4 py-3 hover:bg-slate-50 {{ is_null($notif->read_at) ? 'bg-school-50/40' : '' }}">
                                        <p class="text-xs font-semibold text-slate-900">{{ $notif->data['title'] ?? 'Notification' }}</p>
                                        <p class="text-xs text-slate-600 mt-0.5">{{ $notif->data['message'] ?? '' }}</p>
                                        <p class="text-[10px] text-slate-400 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                                    </a>
                                @empty
                                    <p class="px-4 py-6 text-center text-xs text-slate-500">No notifications yet.</p>
                                @endforelse
                            </div>
                            <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-200 text-center">
                                <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-school-600 hover:underline">
                                    View all notifications &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    @if($school->school_id_code)
                        <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                            School ID: {{ $school->school_id_code }}
                        </span>
                    @endif
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-school-50 text-school-700 border border-school-200">
                        {{ $currentUser->role_labels }}
                    </span>
                </div>
            </div>
        </header>

        {{-- Toast / Flash Notifications --}}
        @if(session('status') || session('error') || $errors->any())
            <div class="px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
                @if(session('status'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
                         class="flex items-center justify-between gap-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                        <span>{{ session('status') }}</span>
                        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-900 font-bold">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show"
                         class="flex items-center justify-between gap-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                        <span>{{ session('error') }}</span>
                        <button type="button" @click="show = false" class="text-rose-600 hover:text-rose-900 font-bold">&times;</button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold mb-1">Please correct the following errors:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>
    </div>
</div>

{{-- Global Quick Jump Command Palette (Ctrl+K) --}}
<div x-show="cmdOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/60 backdrop-blur-sm pt-20 p-4">
    <div @click.away="cmdOpen = false" class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-lg w-full overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center gap-3">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-ref="cmdInput" x-model="cmdQuery" @input="onCmdInput()" @keydown.enter.prevent="goFirstMatch()"
                   placeholder="{{ $currentUser->isAdmin() ? 'Search students, LRN, teachers, sections, or pages...' : 'Jump to a page or module...' }}"
                   class="w-full border-0 focus:ring-0 text-sm text-slate-900 placeholder-slate-400 p-0">
            <button type="button" @click="cmdOpen = false" class="text-[11px] font-mono text-slate-400 hover:text-slate-600 px-1.5 py-0.5 rounded border border-slate-200">ESC</button>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
            <template x-if="liveLoading">
                <div class="px-4 py-2.5 text-xs text-slate-400 bg-slate-50">Searching records...</div>
            </template>
            <template x-for="hit in liveResults" :key="'live-' + hit.url + hit.label">
                <a :href="hit.url" class="flex items-center justify-between px-4 py-2.5 bg-school-50/30 hover:bg-school-50 transition text-sm">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 truncate" x-text="hit.label"></p>
                        <p class="text-[11px] text-slate-500 truncate" x-text="hit.sublabel"></p>
                    </div>
                    <span class="shrink-0 ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-school-100 text-school-800" x-text="hit.group"></span>
                </a>
            </template>
            <template x-for="item in filteredLinks" :key="item.url + item.label">
                <a :href="item.url" class="flex items-center justify-between px-4 py-2.5 hover:bg-school-50 transition text-sm">
                    <span class="font-medium text-slate-800" x-text="item.label"></span>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400" x-text="item.group"></span>
                </a>
            </template>
            <div x-show="!liveLoading && filteredLinks.length === 0 && liveResults.length === 0" class="px-4 py-8 text-center text-xs text-slate-500">
                No matching records or pages found.
            </div>
        </div>
    </div>
</div>
</body>
</html>
