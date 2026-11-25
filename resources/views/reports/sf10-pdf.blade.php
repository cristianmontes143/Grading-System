<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SF10 Permanent Academic Record — {{ $student->formal_name }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #111827; margin: 0; padding: 14px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 2px 0; font-size: 13px; text-transform: uppercase; }
        .header p { margin: 1px 0; color: #374151; font-size: 9px; }
        .box { border: 1px solid #94a3b8; margin-bottom: 10px; }
        .box-title { background: #1e3a8a; color: #fff; font-weight: bold; padding: 4px 8px; font-size: 9.5px; text-transform: uppercase; }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 4px 8px; border: 1px solid #cbd5e1; font-size: 9px; }
        .grades { width: 100%; border-collapse: collapse; }
        .grades th, .grades td { border: 1px solid #cbd5e1; padding: 3.5px 5px; text-align: center; font-size: 9px; }
        .grades th { background: #f1f5f9; font-weight: bold; }
        .grades td.left, .grades th.left { text-align: left; }
        .meta-bar { background: #f8fafc; padding: 5px 8px; border-bottom: 1px solid #cbd5e1; font-size: 8.8px; }
        .footer-bar { background: #f8fafc; padding: 5px 8px; border-top: 1px solid #cbd5e1; font-size: 8.8px; }
        .fail { color: #b91c1c; font-weight: bold; }
        .sig-table { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .sig-table td { width: 50%; text-align: center; padding-top: 24px; }
        .sig-line { border-top: 1px solid #111827; width: 75%; margin: 0 auto 2px; padding-top: 3px; font-weight: bold; }
    </style>
</head>
<body>
<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }} @if($school->school_id_code) &bull; School ID: {{ $school->school_id_code }} @endif</p>
    <p><strong>LEARNER'S PERMANENT ACADEMIC RECORD (SF10 / FORM 137)</strong></p>
</div>

<div class="box">
    <div class="box-title">Learner's Information</div>
    <table class="info-grid">
        <tr>
            <td><strong>Name:</strong> {{ $student->formal_name }}</td>
            <td><strong>LRN:</strong> {{ $student->lrn ?? 'N/A' }}</td>
            <td><strong>Student ID:</strong> {{ $student->student_number }}</td>
        </tr>
        <tr>
            <td><strong>Sex:</strong> {{ $student->gender }}</td>
            <td><strong>Date of Birth:</strong> {{ $student->date_of_birth->format('F d, Y') }}</td>
            <td><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $student->status)) }}</td>
        </tr>
    </table>
</div>

@forelse($records as $item)
    @php
        $enr = $item['enrollment'];
        $sum = $item['summary'];
    @endphp
    <div class="box" style="page-break-inside: avoid;">
        <div class="box-title">
            SY {{ $enr->schoolYear->name }} &mdash; {{ $enr->gradeLevel->name }} ({{ $enr->section->name }}) &bull; {{ $enr->curriculum->name }}
        </div>
        <div class="meta-bar">
            <strong>Adviser:</strong> {{ $enr->section->adviser?->full_name ?? 'Unassigned' }}
            &nbsp;|&nbsp; <strong>Enrollment Status:</strong> {{ ucwords(str_replace('_', ' ', $enr->enrollment_status)) }}
            @if($enr->shsTrack) &nbsp;|&nbsp; <strong>Track:</strong> {{ $enr->shsTrack->name }} @endif
            @if($enr->shsStrand) ({{ $enr->shsStrand->code }}) @endif
        </div>
        <table class="grades">
            <thead>
            <tr>
                <th class="left">Learning Area / Subject</th>
                @foreach($sum['periods'] as $p)
                    <th>{{ $p->code }}</th>
                @endforeach
                <th>Final Rating</th>
                <th class="left">Remarks</th>
            </tr>
            </thead>
            <tbody>
            @foreach($sum['subject_rows'] as $row)
                <tr>
                    <td class="left">{{ $row['subject']->name }}</td>
                    @foreach($sum['periods'] as $p)
                        @php $val = $row['period_grades'][$p->id] ?? null; @endphp
                        <td class="{{ is_numeric($val) && (float) $val < 75 ? 'fail' : '' }}">{{ $val ?? '—' }}</td>
                    @endforeach
                    <td class="{{ ($row['final_grade'] ?? 100) < 75 ? 'fail' : '' }}"><strong>{{ $row['final_grade'] !== null ? number_format($row['final_grade'], 0) : '—' }}</strong></td>
                    <td class="left">{{ $row['remarks'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if($enr->remedialClasses->isNotEmpty())
            <div class="meta-bar" style="border-top: 1px solid #cbd5e1; background: #fffbeb;">
                <strong>Remedial Classes (Recomputed Final Grades)</strong>
                @if($enr->remedialClasses->first()?->conducted_from || $enr->remedialClasses->first()?->conducted_to)
                    &mdash; Conducted: {{ $enr->remedialClasses->first()?->conducted_from?->format('M d, Y') ?? '—' }} to {{ $enr->remedialClasses->first()?->conducted_to?->format('M d, Y') ?? '—' }}
                @endif
            </div>
            <table class="grades">
                <thead>
                <tr>
                    <th class="left">Remedial Learning Area</th>
                    <th>Final Rating</th>
                    <th>Remedial Class Mark</th>
                    <th>Recomputed Final Grade</th>
                    <th class="left">Remarks</th>
                </tr>
                </thead>
                <tbody>
                @foreach($enr->remedialClasses as $rem)
                    <tr>
                        <td class="left">{{ $rem->subject->name }}</td>
                        <td class="fail">{{ number_format($rem->final_rating, 0) }}</td>
                        <td>{{ number_format($rem->remedial_class_mark, 0) }}</td>
                        <td><strong>{{ number_format($rem->recomputed_final_grade, 0) }}</strong></td>
                        <td class="left"><strong>{{ $rem->remarks }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <div class="footer-bar">
            <strong>General Average:</strong> {{ $sum['general_average'] !== null ? number_format($sum['general_average'], 0) : ($sum['is_descriptive_only'] ? 'Descriptive' : 'Incomplete') }}
            &nbsp;|&nbsp; <strong>Action / Status:</strong> {{ ucwords(str_replace('_', ' ', $sum['promotion_status'])) }}
            &nbsp;|&nbsp; <strong>Attendance:</strong> {{ $sum['attendance_totals']['days_present'] }}/{{ $sum['attendance_totals']['school_days'] }} days present
        </div>
    </div>
@empty
    <p>No scholastic enrollment records found for this learner.</p>
@endforelse

<table class="sig-table">
    <tr>
        <td>
            <div class="sig-line">School Registrar / Records Officer</div>
            <div>Certified True and Correct ({{ $generated_at->format('M d, Y') }})</div>
        </td>
        <td>
            <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
            <div>{{ $school->principal_title ?? 'School Principal' }}</div>
        </td>
    </tr>
</table>
</body>
</html>
