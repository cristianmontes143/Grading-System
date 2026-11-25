<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Server Error</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full flex items-center justify-center p-6 text-slate-800">
<div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 p-8 text-center shadow-sm">
    <p class="text-4xl font-extrabold text-rose-600">500</p>
    <h1 class="mt-2 text-lg font-bold text-slate-900">Something Went Wrong</h1>
    <p class="mt-2 text-sm text-slate-600">An unexpected error occurred while processing your request. No grading data was corrupted; please try again or contact your school administrator.</p>
    <a href="{{ url('/dashboard') }}" class="mt-6 inline-block px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
        Return to Dashboard
    </a>
</div>
</body>
</html>
