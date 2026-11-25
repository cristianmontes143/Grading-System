<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consolidated Master Grade Sheet &amp; Honor Roll — {{ $summary['section']->gradeLevel->name }} {{ $summary['section']->name }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 11px; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { margin: 3px 0; color: #4b5563; font-size: 11px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 11px; }
        .kpis { display: flex; gap: 10px; margin-bottom: 14px; }
        .kpi { flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; text-align: center; }
        .kpi .label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi .val { font-size: 15px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: center; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        td.left, th.left { text-align: left; }
        .honor-badge { font-weight: bold; color: #065f46; }
        .risk-badge { font-weight: bold; color: #b91c1c; }
        .signatures { display: flex; justify-content: space-between; margin-top: 32px; }
        .sig-box { width: 42%; text-align: center; }
        .sig-line { border-top: 1px solid #111827; margin-top: 34px; padding-top: 4px; font-weight: bold; }
        .no-print { margin-bottom: 14px; text-align: right; }
        .no-print button { background: #1e3a8a; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        @media print { .no-print { display: none; } body { margin: 8px; } }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print Master Sheet &amp; Honor Roll</button>
</div>

<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }}</p>
    <p><strong>CONSOLIDATED OFFICIAL MASTER GRADE SHEET &amp; HONOR ROLL</strong></p>
</div>

<div class="meta">
    <div>
        <strong>Section:</strong> {{ $summary['section']->gradeLevel->name }} &ndash; {{ $summary['section']->name }}<br>
        <strong>Class Adviser:</strong> {{ $summary['section']->adviser?->full_name ?? 'Unassigned' }}
    </div>
    <div style="text-align: right;">
        <strong>School Year:</strong> {{ $summary['section']->schoolYear->name }}<br>
        <strong>Grading Period:</strong> {{ $summary['period']->name }} ({{ $summary['period']->code }})
    </div>
</div>

<div class="kpis">
    <div class="kpi"><div class="label">Total Learners</div><div class="val">{{ $summary['stats']['total_learners'] }}</div></div>
    <div class="kpi"><div class="label">Section Average</div><div class="val">{{ $summary['stats']['section_average'] !== null ? number_format($summary['stats']['section_average'], 2) : '—' }}</div></div>
    <div class="kpi"><div class="label">Passing Rate</div><div class="val">{{ $summary['stats']['passing_rate'] !== null ? $summary['stats']['passing_rate'] . '%' : '—' }}</div></div>
    <div class="kpi"><div class="label">Academic Honors</div><div class="val">{{ $summary['stats']['total_honors'] }}</div></div>
    <div class="kpi"><div class="label">Needs Intervention</div><div class="val">{{ $summary['stats']['at_risk_count'] }}</div></div>
</div>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th class="left">LRN / ID</th>
        <th class="left">Learner Name</th>
        @foreach($summary['subject_rows'] as $sRow)
            <th>{{ $sRow['subject']->code }}</th>
        @endforeach
        <th>Average</th>
        <th>Rank</th>
        <th class="left">Academic Distinction / Status</th>
    </tr>
    </thead>
    <tbody>
    @forelse($summary['learner_rows'] as $idx => $row)
        <tr>
            <td>{{ $idx + 1 }}</td>
            <td class="left" style="font-family: monospace;">{{ $row['student']->lrn ?? $row['student']->student_number }}</td>
            <td class="left"><strong>{{ $row['student']->formal_name }}</strong></td>
            @foreach($summary['subject_rows'] as $sRow)
                @php $g = $row['subject_grades'][$sRow['subject']->id] ?? null; @endphp
                <td class="{{ is_numeric($g) && (float) $g < 75 ? 'risk-badge' : '' }}">
                    {{ $g ?? 'Pending' }}
                </td>
            @endforeach
            <td><strong>{{ $row['average'] !== null ? number_format($row['average'], 2) : '—' }}</strong></td>
            <td>{{ $row['rank'] ?? '—' }}</td>
            <td class="left {{ $row['honor'] ? 'honor-badge' : (! empty($row['failing_subjects']) ? 'risk-badge' : '') }}">
                {{ $row['status_label'] }}
            </td>
        </tr>
    @empty
        <tr><td colspan="{{ $summary['subject_rows']->count() + 6 }}">No learners enrolled.</td></tr>
    @endforelse
    </tbody>
</table>

@if($summary['honor_roll']->isNotEmpty())
    <h3 style="margin: 12px 0 6px; font-size: 12px; text-transform: uppercase;">Official Academic Honor Roll (DepEd Order No. 36, s. 2016)</h3>
    <table>
        <thead>
        <tr>
            <th>Rank</th>
            <th class="left">Learner Name</th>
            <th>General Average</th>
            <th class="left">Academic Distinction</th>
        </tr>
        </thead>
        <tbody>
        @foreach($summary['honor_roll'] as $hr)
            <tr>
                <td>{{ $hr['rank'] }}</td>
                <td class="left"><strong>{{ $hr['student']->formal_name }}</strong></td>
                <td><strong>{{ number_format((float) $hr['average'], 2) }}</strong></td>
                <td class="left honor-badge">{{ $hr['honor'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="signatures">
    <div class="sig-box">
        <div>Prepared by:</div>
        <div class="sig-line">{{ $summary['section']->adviser?->full_name ?? 'Class Adviser' }}</div>
        <div>Class Adviser</div>
    </div>
    <div class="sig-box">
        <div>Noted / Approved by:</div>
        <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
        <div>{{ $school->principal_title ?? 'School Principal' }}</div>
    </div>
</div>
</body>
</html>
