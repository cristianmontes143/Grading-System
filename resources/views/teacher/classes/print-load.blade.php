<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Official Teaching Load Slip — {{ $teacher->full_name }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 11px; margin: 22px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { margin: 2px 0; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 2px 0; color: #475569; font-size: 10.5px; }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .info-grid td { border: 1px solid #cbd5e1; padding: 6px 10px; }
        table.loads { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.loads th, table.loads td { border: 1px solid #94a3b8; padding: 6px 8px; text-align: left; }
        table.loads th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        .center { text-align: center !important; }
        .sigs { display: flex; justify-content: space-between; margin-top: 38px; }
        .sig { width: 42%; text-align: center; }
        .sig-line { border-top: 1px solid #111827; margin-top: 34px; padding-top: 4px; font-weight: bold; }
        .no-print { text-align: right; margin-bottom: 12px; }
        .no-print button { background: #1e3a8a; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 10px; } }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print Teaching Load Slip</button>
</div>

<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }}</p>
    <p><strong>OFFICIAL FACULTY TEACHING LOAD &amp; ADVISORY ASSIGNMENT SLIP</strong></p>
</div>

<table class="info-grid">
    <tr>
        <td><strong>Faculty Name:</strong> {{ $teacher->full_name }}</td>
        <td><strong>Employee No.:</strong> {{ $teacher->employee_number }}</td>
    </tr>
    <tr>
        <td><strong>Role(s):</strong> {{ $teacher->user?->role_labels }}</td>
        <td><strong>School Year:</strong> {{ $activeSy ? 'SY ' . $activeSy->name : 'All Active' }}</td>
    </tr>
    <tr>
        <td><strong>Specialization:</strong> {{ $teacher->specialization ?? 'General Education' }}</td>
        <td><strong>Email:</strong> {{ $teacher->user?->email }}</td>
    </tr>
</table>

@if($advisorySections->isNotEmpty())
    <h3 style="margin: 0 0 6px; font-size: 12px; text-transform: uppercase; color: #1e3a8a;">Class Advisory Assignment(s)</h3>
    <table class="loads">
        <thead>
        <tr>
            <th>Grade &amp; Section</th>
            <th>Room</th>
            <th class="center">Enrolled Learners</th>
        </tr>
        </thead>
        <tbody>
        @foreach($advisorySections as $sec)
            <tr>
                <td><strong>{{ $sec->gradeLevel->name }} &ndash; {{ $sec->name }}</strong></td>
                <td>{{ $sec->room ?? 'TBA' }}</td>
                <td class="center">{{ $sec->current_enrollments_count }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h3 style="margin: 0 0 6px; font-size: 12px; text-transform: uppercase; color: #1e3a8a;">Subject Teaching Load ({{ $assignments->count() }} Class{{ $assignments->count() === 1 ? '' : 'es' }})</h3>
<table class="loads">
    <thead>
    <tr>
        <th class="center">#</th>
        <th>Subject Code &amp; Name</th>
        <th>Grade &amp; Section</th>
        <th>Curriculum</th>
        <th>Schedule</th>
        <th class="center">Learners</th>
    </tr>
    </thead>
    <tbody>
    @forelse($assignments as $idx => $a)
        <tr>
            <td class="center">{{ $idx + 1 }}</td>
            <td><strong>{{ $a->subject->code }}</strong> &ndash; {{ $a->subject->name }}</td>
            <td>{{ $a->section->gradeLevel->name }} &ndash; {{ $a->section->name }}</td>
            <td>{{ $a->subject->curriculum->code }}</td>
            <td>{{ $a->schedule_summary ?? 'TBA' }}</td>
            <td class="center">{{ (int) ($studentCounts[$a->section_id] ?? 0) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="center">No active subject loads assigned.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="sigs">
    <div class="sig">
        <div>Conformed by:</div>
        <div class="sig-line">{{ $teacher->full_name }}</div>
        <div>Faculty Member</div>
    </div>
    <div class="sig">
        <div>Approved by:</div>
        <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
        <div>{{ $school->principal_title ?? 'School Principal' }}</div>
    </div>
</div>
</body>
</html>
