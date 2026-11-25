<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $school->school_name }} — School Grading Management System</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-[#f4f6fb] text-slate-800 antialiased" x-data="{ mobileMenu: false, previewTab: 'collection', policyTab: 'matatag', openFaq: 1 }">

{{-- Top Gold Accent Line --}}
<div class="h-1 bg-gradient-to-r from-school-800 via-amber-500 to-school-700"></div>

{{-- Navigation Header --}}
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0">
            @if($school->logo_path)
                <img src="{{ asset('storage/' . $school->logo_path) }}" alt="School Logo" class="h-10 w-10 rounded-xl object-cover bg-white border border-slate-200 p-0.5 shadow-sm">
            @else
                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-school-700 to-school-900 text-amber-400 flex items-center justify-center font-extrabold text-sm shadow-sm shrink-0">
                    SG
                </div>
            @endif
            <div class="min-w-0">
                <p class="text-sm sm:text-base font-extrabold text-slate-900 truncate">{{ $school->school_name }}</p>
                <p class="text-[11px] font-medium text-slate-500 truncate">
                    School Grading Management System
                    @if($activeSchoolYear) &bull; SY {{ $activeSchoolYear->name }} @endif
                </p>
            </div>
        </a>

        <nav class="hidden md:flex items-center gap-7 text-sm font-semibold text-slate-600">
            <a href="#workflow" class="hover:text-school-700 transition">How It Works</a>
            <a href="#roles" class="hover:text-school-700 transition">Faculty Portals</a>
            <a href="#matatag" class="hover:text-school-700 transition">DepEd Standards</a>
            <a href="#faq" class="hover:text-school-700 transition">FAQ</a>
        </nav>

        <div class="hidden sm:flex items-center gap-2.5">
            @if($school->parent_portal_enabled ?? true)
                <a href="{{ route('portal.grades.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition">
                    Check Learner Grades
                </a>
            @endif
            @auth
                <a href="{{ route('dashboard') }}" class="school-btn-primary">
                    Open Dashboard &rarr;
                </a>
            @else
                <a href="{{ route('login') }}" class="school-btn-primary">
                    Faculty Sign In &rarr;
                </a>
            @endauth
        </div>

        <button type="button" @click="mobileMenu = !mobileMenu" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Mobile Nav Drawer --}}
    <div x-show="mobileMenu" x-cloak class="md:hidden border-t border-slate-200 bg-white px-4 py-4 space-y-3 text-sm font-semibold">
        <a href="#workflow" @click="mobileMenu = false" class="block text-slate-700 py-1">How It Works</a>
        <a href="#roles" @click="mobileMenu = false" class="block text-slate-700 py-1">Faculty Portals</a>
        <a href="#matatag" @click="mobileMenu = false" class="block text-slate-700 py-1">DepEd Standards</a>
        <a href="#faq" @click="mobileMenu = false" class="block text-slate-700 py-1">FAQ</a>
        @auth
            <a href="{{ route('dashboard') }}" class="school-btn-primary w-full mt-2">Open Dashboard &rarr;</a>
        @else
            <a href="{{ route('login') }}" class="school-btn-primary w-full mt-2">Faculty Sign In &rarr;</a>
        @endauth
    </div>
</header>

{{-- Hero Section --}}
<section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-school-900 to-school-700 text-white">
    {{-- Ambient decorative glow + grid --}}
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-amber-400/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-24 w-96 h-96 rounded-full bg-school-400/15 blur-3xl pointer-events-none"></div>
    <svg class="absolute inset-0 w-full h-full opacity-[0.07] pointer-events-none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <pattern id="hero-grid" width="36" height="36" patternUnits="userSpaceOnUse">
                <path d="M 36 0 L 0 0 0 36" fill="none" stroke="white" stroke-width="1"/>
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#hero-grid)" />
    </svg>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 lg:py-22">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            <div class="lg:col-span-6 space-y-6">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 text-xs font-semibold text-amber-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        DepEd MATATAG &amp; Strengthened SHS Ready
                    </div>
                    @if($activeSchoolYear && $openPeriod)
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-xs font-semibold text-emerald-200">
                            SY {{ $activeSchoolYear->name }} &bull; {{ $openPeriod->name }} Open
                        </div>
                    @endif
                </div>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-[1.12]">
                    Zero manual grade collection. Instant official report cards.
                </h1>

                <p class="text-base text-school-100/90 leading-relaxed max-w-xl">
                    Built for Philippine schools to streamline class record encoding, configurable DepEd grading computation, live adviser grade monitoring, and one-click DepEd-compatible SF9 PDF generation.
                </p>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="school-btn-gold text-sm px-6 py-3">
                            Go to My Dashboard &rarr;
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="school-btn-gold text-sm px-6 py-3">
                            Sign In to Faculty Portal &rarr;
                        </a>
                    @endauth
                    @if($school->parent_portal_enabled ?? true)
                        <a href="{{ route('portal.grades.index') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-white text-sm font-semibold transition">
                            Parent &amp; Learner Grade Portal
                        </a>
                    @endif
                </div>

                {{-- Live Public School Snapshot (Non-PII Aggregate Stats) --}}
                <div class="pt-5 grid grid-cols-3 gap-4 border-t border-white/10 max-w-lg text-xs">
                    <div>
                        <p class="font-extrabold text-xl text-white">{{ ($publicStats['grade_levels'] ?? 13) }} Levels</p>
                        <p class="text-school-200/80">Kinder to Grade 12</p>
                    </div>
                    <div>
                        <p class="font-extrabold text-xl text-amber-300">{{ ($publicStats['curricula'] ?? 4) }} Curricula</p>
                        <p class="text-school-200/80">MATATAG, SHS &amp; Legacy</p>
                    </div>
                    <div>
                        <p class="font-extrabold text-xl text-emerald-300">Audited</p>
                        <p class="text-school-200/80">PostgreSQL Security</p>
                    </div>
                </div>
            </div>

            {{-- Right Interactive Visual Preview: Toggle between Collection Board & Digital Class Record --}}
            <div class="lg:col-span-6">
                <div class="rounded-2xl bg-white text-slate-800 shadow-2xl shadow-slate-950/40 border border-white/20 overflow-hidden">
                    <div class="px-5 py-3 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        </div>
                        <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-lg text-[11px] font-semibold">
                            <button type="button" @click="previewTab = 'collection'"
                                    :class="previewTab === 'collection' ? 'bg-school-600 text-white shadow-sm' : 'text-slate-300 hover:text-white'"
                                    class="px-2.5 py-1 rounded-md transition">
                                Adviser Collection
                            </button>
                            <button type="button" @click="previewTab = 'gradebook'"
                                    :class="previewTab === 'gradebook' ? 'bg-school-600 text-white shadow-sm' : 'text-slate-300 hover:text-white'"
                                    class="px-2.5 py-1 rounded-md transition">
                                Digital Gradebook
                            </button>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300">Live Sync</span>
                    </div>

                    {{-- Preview 1: Automatic Grade Collection --}}
                    <div x-show="previewTab === 'collection'" class="p-6 space-y-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-school-700">Grade 12 – Section A</p>
                                <p class="text-sm font-bold text-slate-900">Subject Grade Submission Monitor</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-school-50 text-school-700">75% Complete</span>
                        </div>

                        <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full w-3/4 rounded-full bg-gradient-to-r from-school-600 to-emerald-500"></div>
                        </div>

                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs">
                            <div class="grid grid-cols-3 bg-slate-50 px-4 py-2 font-bold text-slate-500 uppercase tracking-wider text-[10px]">
                                <span>Subject</span>
                                <span>Assigned Teacher</span>
                                <span class="text-right">Status</span>
                            </div>
                            <div class="grid grid-cols-3 px-4 py-2.5 items-center">
                                <span class="font-semibold text-slate-900">Mathematics</span>
                                <span class="text-slate-600">Subject Teacher</span>
                                <span class="text-right"><span class="px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">Submitted</span></span>
                            </div>
                            <div class="grid grid-cols-3 px-4 py-2.5 items-center">
                                <span class="font-semibold text-slate-900">English</span>
                                <span class="text-slate-600">Subject Teacher</span>
                                <span class="text-right"><span class="px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">Submitted</span></span>
                            </div>
                            <div class="grid grid-cols-3 px-4 py-2.5 items-center">
                                <span class="font-semibold text-slate-900">Programming</span>
                                <span class="text-slate-600">Adviser (Own Load)</span>
                                <span class="text-right"><span class="px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">Submitted</span></span>
                            </div>
                            <div class="grid grid-cols-3 px-4 py-2.5 items-center bg-amber-50/40">
                                <span class="font-semibold text-slate-900">Science</span>
                                <span class="text-slate-600">Subject Teacher</span>
                                <span class="text-right"><span class="px-2 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-800">Pending</span></span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 text-xs">
                            <span class="text-slate-500">Only finalized/submitted grades print on official SF9 PDFs.</span>
                            <span class="font-bold text-school-700">Batch PDF Ready &rarr;</span>
                        </div>
                    </div>

                    {{-- Preview 2: Digital Gradebook Auto-Calculation --}}
                    <div x-show="previewTab === 'gradebook'" x-cloak class="p-6 space-y-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-school-700">DO 015, s. 2026 • WW 20% / PT 50% / EX 30%</p>
                                <p class="text-sm font-bold text-slate-900">Auto-Computed Term Class Record</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700">Auto-Transmuted</span>
                        </div>

                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs">
                            <div class="grid grid-cols-5 bg-slate-50 px-3 py-2 font-bold text-slate-500 uppercase tracking-wider text-[10px]">
                                <span class="col-span-2">Learner</span>
                                <span class="text-center">WW/PT/EX</span>
                                <span class="text-center">Initial</span>
                                <span class="text-right">Grade</span>
                            </div>
                            <div class="grid grid-cols-5 px-3 py-2.5 items-center">
                                <span class="col-span-2 font-semibold text-slate-900">Dela Cruz, Juan</span>
                                <span class="text-center font-mono text-slate-600">18 / 46 / 26</span>
                                <span class="text-center font-mono text-slate-600">90.00</span>
                                <span class="text-right font-extrabold text-emerald-700">92</span>
                            </div>
                            <div class="grid grid-cols-5 px-3 py-2.5 items-center">
                                <span class="col-span-2 font-semibold text-slate-900">Santos, Maria</span>
                                <span class="text-center font-mono text-slate-600">17 / 44 / 24</span>
                                <span class="text-center font-mono text-slate-600">85.00</span>
                                <span class="text-right font-extrabold text-school-700">87</span>
                            </div>
                            <div class="grid grid-cols-5 px-3 py-2.5 items-center">
                                <span class="col-span-2 font-semibold text-slate-900">Reyes, Gabriel</span>
                                <span class="text-center font-mono text-slate-600">19 / 48 / 27</span>
                                <span class="text-center font-mono text-slate-600">94.00</span>
                                <span class="text-right font-extrabold text-emerald-700">95</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 text-xs">
                            <span class="text-slate-500">Supports Excel upload, keyboard navigation &amp; audit logging.</span>
                            <span class="font-bold text-emerald-700">Submit to Adviser &rarr;</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Public School Announcements Strip (only shows when Admin has published Public notices) --}}
@if(isset($publicAnnouncements) && $publicAnnouncements->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
        <div class="school-card overflow-hidden border-school-200/80">
            <div class="px-6 py-3.5 bg-school-50/70 border-b border-school-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-school-900">Official School Advisories &amp; Announcements</h2>
                </div>
                <span class="text-[11px] font-semibold text-school-700">{{ $school->school_name }}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                @foreach($publicAnnouncements as $notice)
                    <div class="p-5 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $notice->category === 'deadline' ? 'bg-rose-100 text-rose-800' : ($notice->category === 'event' ? 'bg-emerald-100 text-emerald-800' : 'bg-school-100 text-school-800') }}">
                                {{ \App\Models\Announcement::categoryLabels()[$notice->category] ?? ucfirst($notice->category) }}
                            </span>
                            <span class="text-[11px] text-slate-400">{{ $notice->created_at->format('M d, Y') }}</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $notice->title }}</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">{{ \Illuminate\Support\Str::limit($notice->body, 170) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Workflow Section --}}
<section id="workflow" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <p class="text-xs font-bold uppercase tracking-widest text-school-600">End-to-End School Workflow</p>
        <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">Simple enough for every teacher, secure enough for the whole school.</h2>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach([
            ['01', 'Configure & Assign', 'Administrator sets up School Years, MATATAG/SHS Curricula, Grading Policies, and assigns Advisers and Subject Teachers across sections.'],
            ['02', 'Encode & Auto-Compute', 'Subject teachers encode quizzes, written work, performance tasks, and exams—or upload Excel sheets. Grades compute accurately in real time.'],
            ['03', 'Submit Automatically', 'Teachers submit finalized grades directly to PostgreSQL. Section advisers are notified immediately with zero manual follow-up.'],
            ['04', 'Generate Official SF9 PDFs', 'Once all required subjects are submitted, advisers generate individual or complete batch-section DepEd-compatible report cards in one click.'],
        ] as [$num, $title, $desc])
            <div class="school-card p-6 hover:border-school-300 hover:-translate-y-0.5 hover:shadow-md transition duration-200">
                <span class="inline-flex items-center justify-center h-10 w-10 rounded-xl bg-school-50 text-school-700 font-extrabold text-sm border border-school-100">
                    {{ $num }}
                </span>
                <h3 class="mt-4 text-base font-bold text-slate-900">{{ $title }}</h3>
                <p class="mt-2 text-xs text-slate-600 leading-relaxed">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Role Portals Section --}}
<section id="roles" class="bg-white border-y border-slate-200/80 py-16 lg:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <p class="text-xs font-bold uppercase tracking-widest text-school-600">Role-Based Access Control</p>
            <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">Dedicated Workspaces for Every School Role</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-2xl border border-slate-200 p-6 bg-slate-50/50 space-y-3 hover:shadow-md transition">
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-slate-900 text-white">Administrator</span>
                <h3 class="text-lg font-bold text-slate-900">School-Wide Control &amp; Governance</h3>
                <ul class="space-y-2 text-xs text-slate-600">
                    <li>&bull; Manage School Years, MATATAG Curricula &amp; Grading Policies</li>
                    <li>&bull; Register Teachers, Students (with Excel/CSV Import &amp; Transfers)</li>
                    <li>&bull; Lock/Unlock Grading Periods &amp; Authorize Audited Overrides</li>
                    <li>&bull; Inspect Immutable Audit Logs &amp; Database Backups</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-school-200 p-6 bg-school-50/40 space-y-3 hover:shadow-md transition">
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-school-600 text-white">Teacher 1 — Adviser &amp; Subject Teacher</span>
                <h3 class="text-lg font-bold text-slate-900">Advisory Oversight + Own Classes</h3>
                <ul class="space-y-2 text-xs text-slate-600">
                    <li>&bull; Monitor real-time subject submission status in advisory section</li>
                    <li>&bull; Record Monthly Attendance &amp; DepEd Observed Core Values</li>
                    <li>&bull; Generate Individual &amp; Batch-Section Official PDF Report Cards</li>
                    <li>&bull; Encode grades for own subjects without editing co-teachers' grades</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 p-6 bg-slate-50/50 space-y-3 hover:shadow-md transition">
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-amber-500 text-slate-950">Teacher 2 — Subject Teacher</span>
                <h3 class="text-lg font-bold text-slate-900">Fast, Accurate Digital Class Record</h3>
                <ul class="space-y-2 text-xs text-slate-600">
                    <li>&bull; Strict access scoped only to assigned sections and subjects</li>
                    <li>&bull; Bulk score entry &amp; Excel template import with max-score validation</li>
                    <li>&bull; Automatic PS, WS, Initial &amp; Transmuted/Zero-Based calculation</li>
                    <li>&bull; One-click grade submission &amp; printable/Excel subject grade sheets</li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- Interactive DepEd MATATAG & Policy Explorer --}}
<section id="matatag" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="text-center max-w-2xl mx-auto mb-10">
        <p class="text-xs font-bold uppercase tracking-widest text-school-600">Philippine Basic Education Standards</p>
        <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">Interactive DepEd Curriculum &amp; Assessment Explorer</h2>
        <p class="mt-2 text-sm text-slate-500">Every weight, transmutation table, and curriculum mapping is database-driven and administrator-configurable.</p>
    </div>

    <div class="school-card overflow-hidden">
        <div class="bg-slate-50 border-b border-slate-200 px-4 sm:px-6 py-3 flex flex-wrap gap-2">
            @foreach([
                ['matatag', 'MATATAG K–10 (DO 010)'],
                ['do015', 'Revised Assessment (DO 015)'],
                ['shs', 'Strengthened SHS (DO 017)'],
                ['sf9', 'SF9 Report Cards & History'],
            ] as [$key, $label])
                <button type="button" @click="policyTab = '{{ $key }}'"
                        :class="policyTab === '{{ $key }}' ? 'bg-school-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="p-6 sm:p-8">
            <div x-show="policyTab === 'matatag'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-7 space-y-3">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-school-50 text-school-700">DepEd Order No. 010, s. 2024</span>
                    <h3 class="text-xl font-extrabold text-slate-900">Phased MATATAG K–10 Curriculum Implementation</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Supports simultaneous multi-version curricula across grade levels within the same School Year (e.g., MATATAG for Kinder, Grades 1–4, 7–8 alongside Legacy K–12 for Grades 5–6 and 9–10) without ever mixing subject catalogs.
                    </p>
                </div>
                <div class="lg:col-span-5 grid grid-cols-2 gap-3 text-xs">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                        <p class="font-bold text-slate-900">Key Stage 1 (K–G3)</p>
                        <p class="text-slate-500 mt-1">GMRC, Makabansa, Language, Reading &amp; Literacy, Math</p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                        <p class="font-bold text-slate-900">Grades 4–10</p>
                        <p class="text-slate-500 mt-1">Values Education, Science, Math, English, Filipino, AP, EPP/TLE, MAPEH</p>
                    </div>
                </div>
            </div>

            <div x-show="policyTab === 'do015'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-7 space-y-3">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900">DepEd Order No. 015, s. 2026</span>
                    <h3 class="text-xl font-extrabold text-slate-900">Trimester Calendar, Descriptive KS1 &amp; Transmutation Transition</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Supports 3-term trimester periods (T1–T3), Key Stage 1 descriptive progress ratings (CO / DV / BG), nested Examination sub-components (ST1 30% + ST2 30% + TE 40%), transition transmutation (SY 2026–2027), and zero-based grading (SY 2027–2028+).
                    </p>
                </div>
                <div class="lg:col-span-5 space-y-2 text-xs">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="font-semibold text-slate-800">Written / Oral Works (WW)</span>
                        <span class="font-bold text-school-700">20%</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="font-semibold text-slate-800">Performance Tasks (PT)</span>
                        <span class="font-bold text-school-700">50%</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="font-semibold text-slate-800">Examinations (ST1 / ST2 / TE)</span>
                        <span class="font-bold text-school-700">30%</span>
                    </div>
                </div>
            </div>

            <div x-show="policyTab === 'shs'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-7 space-y-3">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">DO 017 &amp; DM 012, s. 2026</span>
                    <h3 class="text-xl font-extrabold text-slate-900">Strengthened Senior High School (Academic &amp; TechPro)</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Organizes Grade 11 and Grade 12 sections by Strengthened SHS Tracks (Academic, Technical-Professional) or Legacy Tracks (STEM, ABM, HUMSS, GAS, TVL) with subject-specific grading weight overrides for TechPro practicum and Work Immersion.
                    </p>
                </div>
                <div class="lg:col-span-5 grid grid-cols-2 gap-3 text-xs">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                        <p class="font-bold text-slate-900">Core &amp; Academic</p>
                        <p class="text-slate-500 mt-1">WW 20% / PT 50% / EX 30% (configurable per subject)</p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                        <p class="font-bold text-slate-900">TechPro / Immersion</p>
                        <p class="text-slate-500 mt-1">Practice-heavy PT weights with full audit trail</p>
                    </div>
                </div>
            </div>

            <div x-show="policyTab === 'sf9'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-7 space-y-3">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-900 text-white">SF9 / Form 138 Compliance</span>
                    <h3 class="text-xl font-extrabold text-slate-900">Official PDF Report Cards &amp; Historical Accuracy</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Every enrollment snapshots its exact curriculum version. Historical report cards always render under the original subjects and grading rules active when earned, while official batch PDFs enforce strict missing-grade verification.
                    </p>
                </div>
                <div class="lg:col-span-5 space-y-2 text-xs">
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 font-semibold">
                        &check; Official PDF requires 100% submitted subject grades
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 font-semibold">
                        &check; Incomplete sections clearly watermark Draft previews
                    </div>
                    <div class="p-3 rounded-xl bg-school-50 border border-school-200 text-school-900 font-semibold">
                        &check; Includes Monthly Attendance &amp; DepEd Core Values (AO/SO/RO/NO)
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAQ Accordion --}}
<section id="faq" class="bg-white border-t border-slate-200/80 py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <p class="text-xs font-bold uppercase tracking-widest text-school-600">Common Questions</p>
            <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">Governance, Security &amp; Grading FAQ</h2>
        </div>

        <div class="space-y-3">
            @foreach([
                [1, 'Can a Section Adviser edit grades encoded by another Subject Teacher?', 'No. Role-based access policies strictly enforce subject ownership. Section Advisers can view finalized/submitted grades for all subjects in their advisory section to generate SF9 report cards, and can encode grades only for subjects explicitly assigned to them.'],
                [2, 'What happens if a subject teacher is reassigned mid-school year?', 'Previous teacher assignments are preserved as inactive for complete historical accuracy. Any already-submitted grades remain locked and visible on the new teacher\'s gradebook and Adviser collection board, while an Administrator can return a period for revision if the new teacher needs to re-encode it.'],
                [3, 'Can we customize grading weights or transmutation tables when DepEd updates policies?', 'Yes. Administrators can configure grading component weights, nested examination sub-components, transmutation tables, and zero-based grading policies per School Year, Curriculum, Grade Level, or Subject directly through the Admin panel without modifying code.'],
                [4, 'How are grade changes tracked for accountability?', 'Every score entry, score edit, grade submission, return for revision, period lock/unlock, and Administrator grade override is recorded permanently in an immutable PostgreSQL audit log with actor identity, timestamp, previous/new values, and reason.'],
            ] as [$id, $question, $answer])
                <div class="rounded-xl border border-slate-200 bg-slate-50/50 overflow-hidden">
                    <button type="button" @click="openFaq = openFaq === {{ $id }} ? null : {{ $id }}"
                            class="w-full px-5 py-4 text-left flex items-center justify-between gap-4 hover:bg-slate-50 transition">
                        <span class="text-sm font-bold text-slate-900">{{ $question }}</span>
                        <svg class="w-4 h-4 text-slate-500 shrink-0 transition-transform" :class="openFaq === {{ $id }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="openFaq === {{ $id }}" x-cloak class="px-5 pb-4 text-xs text-slate-600 leading-relaxed border-t border-slate-200/70 pt-3 bg-white">
                        {{ $answer }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA Banner --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-school-900 to-school-800 text-white p-8 sm:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 shadow-lg">
        <div class="space-y-2 max-w-2xl">
            <span class="text-xs font-bold uppercase tracking-widest text-amber-300">Faculty &amp; Administration Access</span>
            <h2 class="text-2xl font-extrabold">Ready to encode grades or monitor section submissions?</h2>
            <p class="text-sm text-school-100/90">
                Sign in with your school-issued faculty account to access your personalized workspace.
            </p>
        </div>
        <div class="shrink-0">
            @auth
                <a href="{{ route('dashboard') }}" class="school-btn-gold px-6 py-3">Open Portal &rarr;</a>
            @else
                <a href="{{ route('login') }}" class="school-btn-gold px-6 py-3">Sign In Now &rarr;</a>
            @endauth
        </div>
    </div>
</section>

{{-- Institutional Multi-Column Footer --}}
<footer class="bg-white border-t border-slate-200 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-8 text-xs text-slate-500">
        <div class="space-y-2">
            <p class="text-sm font-extrabold text-slate-900">{{ $school->school_name }}</p>
            <p>
                {{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') ?: 'Department of Education — Basic Education Portal' }}
            </p>
            @if($school->school_id_code)
                <p class="font-mono text-slate-600">DepEd School ID: {{ $school->school_id_code }}</p>
            @endif
        </div>

        <div class="space-y-1.5">
            <p class="font-bold uppercase tracking-wider text-slate-700 text-[11px]">School Office &amp; Leadership</p>
            @if($school->principal_name)
                <p class="font-semibold text-slate-800">{{ $school->principal_name }} <span class="font-normal text-slate-500">&mdash; {{ $school->principal_title ?? 'School Principal' }}</span></p>
            @endif
            @if($school->address)
                <p>{{ $school->address }}</p>
            @endif
            @if($school->email || $school->contact_number)
                <p>{{ collect([$school->email, $school->contact_number])->filter()->implode(' • ') }}</p>
            @endif
        </div>

        <div class="md:text-right space-y-1.5">
            <p class="font-bold uppercase tracking-wider text-slate-700 text-[11px]">Portal Security</p>
            <p>&copy; {{ date('Y') }} {{ $school->school_name }}. All rights reserved.</p>
            <p class="text-[11px] text-slate-400">Protected with Laravel RBAC, CSRF Protection, Rate Limiting &amp; Immutable PostgreSQL Audit Logs</p>
        </div>
    </div>
</footer>
</body>
</html>
