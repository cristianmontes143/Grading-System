<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DepEd MPS &amp; SARDO Report — SY {{ $selectedSchoolYear?->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 20px; }
        .no-print { text-align: right; margin-bottom: 12px; }
        .no-print button { padding: 7px 14px; background: #1e3a8a; color: #fff; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { margin: 3px 0; font-size: 16px; text-transform: uppercase; color: #1e3a8a; }
        .header p { margin: 1px 0; font-size: 10px; color: #475569; }
        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .kpi-grid td { border: 1px solid #cbd5e1; padding: 8px; text-align: center; background: #f8fafc; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi-val { font-size: 16px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        h2 { font-size: 12.5px; text-transform: uppercase; color: #1e3a8a; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin: 16px 0 8px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 5px 7px; font-size: 10px; }
        table.data th { background: #f1f5f9; text-align: left; text-transform: uppercase; }
        .center { text-align: center; }
        .fail { color: #b91c1c; font-weight: bold; }
        .pass { color: #047857; font-weight: bold; }
        .sigs { width: 100%; margin-top: 34px; border-collapse: collapse; }
        .sigs td { width: 50%; text-align: center; padding-top: 24px; }
        .sig-line { border-top: 1px solid #0f172a; width: 70%; margin: 0 auto 3px; padding-top: 4px; font-weight: bold; }
        @media print { .no-print { display: none; } body { margin: 8px; } }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print Report</button>
</div>

<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }}</p>
    <p><strong>DEPED MEAN PERCENTAGE SCORE (MPS) &amp; SARDO ATTENDANCE REPORT</strong></p>
    <p>SY {{ $selectedSchoolYear?->name ?? '—' }} &bull; {{ $selectedPeriod ? $selectedPeriod->name : 'All Grading Periods' }} &bull; Generated {{ $generatedAt->format('F d, Y h:i A') }}</p>
</div>

<table class="kpi-grid">
    <tr>
        <td>
            <div class="kpi-label">School-Wide MPS</div>
            <div class="kpi-val">{{ $mps['overall_mps'] !== null ? number_format($mps['overall_mps'], 2) . '%' : '—' }}</div>
        </td>
        <td>
            <div class="kpi-label">Classes &ge; 75% Target</div>
            <div class="kpi-val">{{ $mps['classes_meeting_target'] }} / {{ $mps['classes_evaluated'] }}</div>
        </td>
        <td>
            <div class="kpi-label">Overall Attendance Rate</div>
            <div class="kpi-val">{{ $sardo['overall_attendance_rate'] !== null ? number_format($sardo['overall_attendance_rate'], 1) . '%' : '—' }}</div>
        </td>
        <td>
            <div class="kpi-label">Critical SARDO (&ge;20%)</div>
            <div class="kpi-val" style="color: #b91c1c;">{{ $sardo['critical_count'] }}</div>
        </td>
    </tr>
</table>

<h2>1. Learning Area Mean Percentage Score (MPS)</h2>
<table class="data">
    <thead>
    <tr>
        <th>Learning Area</th>
        <th class="center">Classes</th>
        <th class="center">Assessments</th>
        <th class="center">MPS (%)</th>
        <th>Mastery Equivalent</th>
        <th class="center">75% Target</th>
    </tr>
    </thead>
    <tbody>
    @forelse($mps['subject_rows'] as $row)
        <tr>
            <td><strong>{{ $row['subject_name'] }}</strong> ({{ $row['subject_code'] }})</td>
            <td class="center">{{ $row['classes_count'] }}</td>
            <td class="center">{{ $row['assessments_count'] }}</td>
            <td class="center {{ $row['meets_target'] ? 'pass' : 'fail' }}">{{ number_format($row['mps'], 2) }}%</td>
            <td>{{ $row['mastery_level'] }}</td>
            <td class="center {{ $row['meets_target'] ? 'pass' : 'fail' }}">{{ $row['meets_target'] ? 'MET' : 'BELOW TARGET' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="center">No scored assessments available.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>2. SARDO (Students At Risk of Dropping Out) Attendance Alert List</h2>
<table class="data">
    <thead>
    <tr>
        <th>Learner Name</th>
        <th>Grade &amp; Section</th>
        <th>Adviser</th>
        <th class="center">Days Absent / Total</th>
        <th class="center">Tardy</th>
        <th class="center">Absence %</th>
        <th class="center">Alert Tier</th>
    </tr>
    </thead>
    <tbody>
    @forelse($sardo['alerts'] as $a)
        <tr>
            <td><strong>{{ $a['student']->formal_name }}</strong></td>
            <td>{{ $a['grade_level']->name }} – {{ $a['section']->name }}</td>
            <td>{{ $a['section']->adviser?->full_name ?? 'Unassigned' }}</td>
            <td class="center">{{ $a['days_absent'] }} / {{ $a['school_days'] }}</td>
            <td class="center">{{ $a['days_tardy'] }}</td>
            <td class="center {{ $a['severity'] === 'critical' ? 'fail' : '' }}">{{ number_format($a['absence_rate'], 1) }}%</td>
            <td class="center">{{ strtoupper(str_replace('_', ' ', $a['severity'])) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="center">No SARDO attendance alerts detected.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="sigs">
    <tr>
        <td>
            <div class="sig-line">School Guidance / Registrar Coordinator</div>
            <div>Prepared &amp; Verified By</div>
        </td>
        <td>
            <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
            <div>{{ $school->principal_title ?? 'School Principal' }}</div>
        </td>
    </tr>
</table>
</body>
</html>
