<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Executive Academic Performance Report — {{ $school->school_name }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 11px; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { margin: 2px 0; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 2px 0; color: #475569; font-size: 10.5px; }
        .kpis { display: flex; gap: 10px; margin-bottom: 16px; }
        .kpi { flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; text-align: center; }
        .kpi .lbl { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi .val { font-size: 16px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        h2 { font-size: 12px; text-transform: uppercase; color: #1e3a8a; margin: 16px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: center; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        td.left, th.left { text-align: left; }
        .sigs { display: flex; justify-content: flex-end; margin-top: 36px; }
        .sig-box { width: 42%; text-align: center; }
        .sig-line { border-top: 1px solid #111827; margin-top: 32px; padding-top: 4px; font-weight: bold; }
        .no-print { text-align: right; margin-bottom: 12px; }
        .no-print button { background: #1e3a8a; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 8px; } }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print Executive Academic Report</button>
</div>

<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }} @if($school->school_id_code) &bull; School ID: {{ $school->school_id_code }} @endif</p>
    <p><strong>SCHOOL-WIDE EXECUTIVE ACADEMIC PERFORMANCE &amp; HONOR ROLL REPORT</strong></p>
    <p>SY {{ $selectedSy?->name ?? 'N/A' }} &bull; {{ $selectedPeriod ? $selectedPeriod->name . ' (' . $selectedPeriod->code . ')' : 'All Periods' }} &bull; Generated {{ $generatedAt->format('F d, Y') }}</p>
</div>

<div class="kpis">
    <div class="kpi"><div class="lbl">Evaluated Learners</div><div class="val">{{ $totals['learners'] }}</div></div>
    <div class="kpi"><div class="lbl">School Average</div><div class="val">{{ $totals['overall_average'] !== null ? number_format($totals['overall_average'], 2) : '—' }}</div></div>
    <div class="kpi"><div class="lbl">Passing Rate</div><div class="val">{{ $totals['passing_rate'] !== null ? $totals['passing_rate'] . '%' : '—' }}</div></div>
    <div class="kpi"><div class="lbl">Academic Honors</div><div class="val">{{ $totals['total_honors'] }}</div></div>
    <div class="kpi"><div class="lbl">Needs Intervention</div><div class="val">{{ $totals['at_risk'] }}</div></div>
</div>

<h2>1. DepEd 5-Band Level of Progress &amp; Achievement</h2>
<table>
    <thead>
    <tr>
        <th class="left">Descriptor &amp; Scale</th>
        <th>Outstanding (90–100)</th>
        <th>Very Satisfactory (85–89)</th>
        <th>Satisfactory (80–84)</th>
        <th>Fairly Satisfactory (75–79)</th>
        <th>Did Not Meet (&lt; 75)</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td class="left"><strong>Learners (Complete Official Grades)</strong></td>
        <td><strong>{{ $totals['distribution']['outstanding'] }}</strong></td>
        <td><strong>{{ $totals['distribution']['very_satisfactory'] }}</strong></td>
        <td><strong>{{ $totals['distribution']['satisfactory'] }}</strong></td>
        <td><strong>{{ $totals['distribution']['fairly_satisfactory'] }}</strong></td>
        <td><strong>{{ $totals['distribution']['did_not_meet'] }}</strong></td>
    </tr>
    </tbody>
</table>

<h2>2. Section-by-Section Performance Comparison</h2>
<table>
    <thead>
    <tr>
        <th class="left">Section</th>
        <th class="left">Adviser</th>
        <th>Learners</th>
        <th>Submission %</th>
        <th>Section Avg</th>
        <th>Honors</th>
        <th>At-Risk</th>
    </tr>
    </thead>
    <tbody>
    @forelse($sectionMetrics as $row)
        <tr>
            <td class="left"><strong>{{ $row['section']->gradeLevel->name }} &ndash; {{ $row['section']->name }}</strong></td>
            <td class="left">{{ $row['section']->adviser?->full_name ?? 'Unassigned' }}</td>
            <td>{{ $row['stats']['total_learners'] }}</td>
            <td>{{ $row['progress_percent'] }}%</td>
            <td><strong>{{ $row['stats']['section_average'] !== null ? number_format($row['stats']['section_average'], 2) : '—' }}</strong></td>
            <td>{{ $row['stats']['total_honors'] }}</td>
            <td>{{ $row['stats']['at_risk_count'] }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No sections available for this filter.</td></tr>
    @endforelse
    </tbody>
</table>

@if($topHonors->isNotEmpty())
    <h2>3. School-Wide Academic Honor Roll (DepEd Order No. 36, s. 2016)</h2>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th class="left">Learner Name</th>
            <th class="left">Section</th>
            <th>General Average</th>
            <th class="left">Academic Distinction</th>
        </tr>
        </thead>
        <tbody>
        @foreach($topHonors as $i => $hr)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="left"><strong>{{ $hr['student']->formal_name }}</strong></td>
                <td class="left">{{ $hr['section']->gradeLevel->code }} &ndash; {{ $hr['section']->name }}</td>
                <td><strong>{{ number_format((float) $hr['average'], 2) }}</strong></td>
                <td class="left">{{ $hr['honor'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="sigs">
    <div class="sig-box">
        <div>Certified &amp; Noted by:</div>
        <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
        <div>{{ $school->principal_title ?? 'School Principal' }}</div>
    </div>
</div>
</body>
</html>
