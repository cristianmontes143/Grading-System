<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Classroom Roster — {{ $section->gradeLevel->name }} {{ $section->name }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 10.5px; margin: 18px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 2px 0; font-size: 15px; text-transform: uppercase; letter-spacing: 0.4px; }
        .header p { margin: 2px 0; color: #475569; font-size: 10px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 10.5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #94a3b8; padding: 4px 5px; }
        th { background: #f1f5f9; font-size: 9.5px; text-transform: uppercase; text-align: center; }
        td.left, th.left { text-align: left; }
        td.center { text-align: center; }
        .group-row td { background: #e0e7ff; font-weight: bold; color: #1e3a8a; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
        .summary { display: flex; gap: 16px; font-size: 10.5px; margin-bottom: 24px; }
        .summary span { background: #f8fafc; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 4px; }
        .sig-row { display: flex; justify-content: space-between; margin-top: 28px; }
        .sig-box { width: 42%; text-align: center; }
        .sig-line { border-top: 1px solid #111827; margin-top: 30px; padding-top: 4px; font-weight: bold; }
        .no-print { text-align: right; margin-bottom: 10px; }
        .no-print button { background: #1e3a8a; color: #fff; border: 0; padding: 7px 14px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 8px; } }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print Classroom Roster</button>
</div>

<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }} @if($school->school_id_code) &bull; School ID: {{ $school->school_id_code }} @endif</p>
    <p><strong>OFFICIAL CLASSROOM ROSTER &amp; CHECKLIST SHEET</strong></p>
</div>

<div class="meta">
    <div>
        <strong>Grade &amp; Section:</strong> {{ $section->gradeLevel->name }} &ndash; {{ $section->name }}<br>
        <strong>Class Adviser:</strong> {{ $section->adviser?->full_name ?? 'Unassigned' }}
        @if(! empty($subjectLabel))
            <br><strong>Subject:</strong> {{ $subjectLabel }}
        @endif
    </div>
    <div style="text-align: right;">
        <strong>School Year:</strong> {{ $section->schoolYear->name }}<br>
        <strong>Room:</strong> {{ $section->room ?? 'TBA' }}
        @if(! empty($teacherName))
            <br><strong>Subject Teacher:</strong> {{ $teacherName }}
        @endif
    </div>
</div>

@php
    $males = $enrollments->filter(fn ($e) => $e->student->gender === 'Male')->values();
    $females = $enrollments->filter(fn ($e) => $e->student->gender !== 'Male')->values();
    $checkCols = 12;
@endphp

<table>
    <thead>
    <tr>
        <th style="width: 26px;">#</th>
        <th class="left" style="width: 100px;">LRN / ID</th>
        <th class="left">Learner Name (Last, First, Middle)</th>
        @for($c = 1; $c <= $checkCols; $c++)
            <th style="width: 24px;">{{ $c }}</th>
        @endfor
        <th style="width: 68px;">Remarks</th>
    </tr>
    </thead>
    <tbody>
    <tr class="group-row"><td colspan="{{ $checkCols + 4 }}">Male Learners ({{ $males->count() }})</td></tr>
    @forelse($males as $i => $enr)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td class="left" style="font-family: monospace; font-size: 9.5px;">{{ $enr->student->lrn ?? $enr->student->student_number }}</td>
            <td class="left">{{ $enr->student->formal_name }}</td>
            @for($c = 1; $c <= $checkCols; $c++)
                <td></td>
            @endfor
            <td></td>
        </tr>
    @empty
        <tr><td colspan="{{ $checkCols + 4 }}" class="center" style="color: #64748b;">No male learners enrolled.</td></tr>
    @endforelse

    <tr class="group-row"><td colspan="{{ $checkCols + 4 }}">Female Learners ({{ $females->count() }})</td></tr>
    @forelse($females as $i => $enr)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td class="left" style="font-family: monospace; font-size: 9.5px;">{{ $enr->student->lrn ?? $enr->student->student_number }}</td>
            <td class="left">{{ $enr->student->formal_name }}</td>
            @for($c = 1; $c <= $checkCols; $c++)
                <td></td>
            @endfor
            <td></td>
        </tr>
    @empty
        <tr><td colspan="{{ $checkCols + 4 }}" class="center" style="color: #64748b;">No female learners enrolled.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="summary">
    <span><strong>Male:</strong> {{ $males->count() }}</span>
    <span><strong>Female:</strong> {{ $females->count() }}</span>
    <span><strong>Total Enrolled:</strong> {{ $enrollments->count() }}</span>
</div>

<div class="sig-row">
    <div class="sig-box">
        <div>Prepared by:</div>
        <div class="sig-line">{{ $teacherName ?? ($section->adviser?->full_name ?? 'Class Adviser') }}</div>
        <div>{{ ! empty($teacherName) ? 'Subject Teacher' : 'Class Adviser' }}</div>
    </div>
    <div class="sig-box">
        <div>Noted by:</div>
        <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
        <div>{{ $school->principal_title ?? 'School Principal' }}</div>
    </div>
</div>
</body>
</html>
