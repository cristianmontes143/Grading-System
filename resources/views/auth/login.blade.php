<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ $school->school_name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="min-h-full font-sans antialiased bg-slate-100 text-slate-800">
<div class="min-h-screen flex flex-col lg:flex-row">
    {{-- Left Academic Brand Hero Panel --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-[54%] bg-gradient-to-br from-slate-950 via-school-800 to-school-700 text-white p-12 xl:p-16 flex-col justify-between relative overflow-hidden">
        {{-- Subtle geometric academic grid overlay --}}
        <svg class="absolute inset-0 w-full h-full opacity-[0.07] pointer-events-none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
        </svg>

        <div class="relative z-10 flex items-center gap-3.5">
            @if($school->logo_path)
                <img src="{{ asset('storage/' . $school->logo_path) }}" alt="School Logo" class="h-12 w-12 rounded-xl object-cover bg-white p-1 shadow-md">
            @else
                <div class="h-12 w-12 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-extrabold text-lg shadow-md">
                    SG
                </div>
            @endif
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-300">DepEd MATATAG Ready</p>
                <p class="text-base font-bold text-white leading-tight">{{ $school->school_name }}</p>
            </div>
        </div>

        <div class="relative z-10 max-w-lg space-y-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-xs font-medium text-school-100">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                School Grading Management System
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight leading-tight">
                Accurate grading &amp; effortless report cards, all in one place.
            </h1>
            <p class="text-sm text-school-100/90 leading-relaxed">
                Subject teachers encode and submit grades directly online. Class advisers monitor submissions in real time and generate DepEd-compatible SF9 PDF report cards automatically—no manual collection required.
            </p>

            <div class="grid grid-cols-1 gap-3 pt-2">
                <div class="flex items-start gap-3 rounded-xl bg-white/10 border border-white/10 p-3.5">
                    <div class="h-8 w-8 rounded-lg bg-amber-500/20 text-amber-300 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">Automatic Grade Collection</p>
                        <p class="text-xs text-school-100/80">Live submission tracking per section with official-only report card gating.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 rounded-xl bg-white/10 border border-white/10 p-3.5">
                    <div class="h-8 w-8 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">MATATAG, Strengthened SHS &amp; Legacy Transition</p>
                        <p class="text-xs text-school-100/80">Configurable DO 015 s. 2026 &amp; DO 8 s. 2015 weights, transmutation, and descriptive grading.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 text-xs text-school-200/80 flex items-center justify-between">
            <span>{{ $school->region ? $school->region . ' • ' : '' }}{{ $school->division ?? 'Official Faculty Portal' }}</span>
            <span>Secured by Audit Trail &amp; RBAC</span>
        </div>
    </div>

    {{-- Right Login Form Panel --}}
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-[#f4f6fa]">
        <div class="w-full max-w-md space-y-6">
            {{-- Mobile Brand Header --}}
            <div class="lg:hidden text-center space-y-2">
                @if($school->logo_path)
                    <img src="{{ asset('storage/' . $school->logo_path) }}" alt="School Logo" class="mx-auto h-14 w-14 rounded-xl object-cover bg-white p-1 shadow">
                @else
                    <div class="mx-auto h-14 w-14 rounded-xl bg-school-700 text-amber-300 flex items-center justify-center text-xl font-extrabold shadow">
                        SG
                    </div>
                @endif
                <h1 class="text-xl font-bold text-slate-900">{{ $school->school_name }}</h1>
                <p class="text-xs text-slate-500">School Grading Management System</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/70 border border-slate-200/80 overflow-hidden">
                <div class="h-1.5 bg-gradient-to-r from-school-700 via-school-500 to-amber-500"></div>
                <div class="p-7 sm:p-9">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900">Faculty Sign In</h2>
                        <p class="text-sm text-slate-500 mt-1">Sign in with your school administrator or teacher account.</p>
                    </div>

                    @if(session('status'))
                        <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5"
                          x-data="{ submitting: false, showPassword: false }" @submit="submitting = true">
                        @csrf

                        <div>
                            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                   class="w-full rounded-xl border-slate-300 bg-slate-50/50 focus:bg-white shadow-sm focus:border-school-500 focus:ring-school-500 text-sm py-2.5 px-3.5"
                                   placeholder="name@school.edu.ph">
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Password</label>
                            <div class="relative">
                                <input id="password" name="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password"
                                       class="w-full rounded-xl border-slate-300 bg-slate-50/50 focus:bg-white shadow-sm focus:border-school-500 focus:ring-school-500 text-sm py-2.5 pl-3.5 pr-11"
                                       placeholder="Enter your password">
                                <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-500 hover:text-slate-800"
                                        x-text="showPassword ? 'Hide' : 'Show'"></button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="remember" class="rounded border-slate-300 text-school-600 focus:ring-school-500">
                                <span>Keep me signed in</span>
                            </label>
                        </div>

                        <button type="submit" :disabled="submitting"
                                class="w-full py-3 px-4 rounded-xl bg-school-600 hover:bg-school-700 text-white font-semibold text-sm shadow-md shadow-school-600/20 transition disabled:opacity-60">
                            <span x-show="!submitting">Sign In to Portal &rarr;</span>
                            <span x-show="submitting" x-cloak>Authenticating...</span>
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-slate-500">
                Authorized school personnel only. All grade encoding and modifications are recorded in an immutable audit log.
            </p>
        </div>
    </div>
</div>
</body>
</html>
