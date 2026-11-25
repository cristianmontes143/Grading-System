<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Recomputed Final Grade — {{ $enrollment->student->formal_name }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #111827; padding: 24px 30px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { margin: 3px 0; font-size: 15px; text-transform: uppercase; color: #1e3a8a; }
        .header p { margin: 1px 0; font-size: 9.5px; color: #374151; }
        .title { text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 0.6px; text-transform: uppercase; margin: 16px 0 12px; color: #0f172a; }
        .intro { line-height: 1.65; text-align: justify; margin-bottom: 14px; }
        table.remedial { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        table.remedial th, table.remedial td { border: 1px solid #94a3b8; padding: 6px 8px; text-align: center; font-size: 9.5px; }
        table.remedial th { background: #1e3a8a; color: #fff; text-transform: uppercase; }
        table.remedial td.left { text-align: left; }
        .passed { color: #047857; font-weight: bold; }
        .failed { color: #b91c1c; font-weight: bold; }
        .sigs { width: 100%; margin-top: 38px; border-collapse: collapse; }
        .sigs td { width: 50%; text-align: center; vertical-align: bottom; padding-top: 28px; }
        .sig-line { border-top: 1px solid #111827; width: 78%; margin: 0 auto 3px; padding-top: 4px; font-weight: bold; }
    </style>
</head>
<body>
<div class="header">
    <p>Republic of the Philippines &bull; Department of Education</p>
    <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }}</p>
    <h1>{{ $school->school_name }}</h1>
    <p>{{ $school->address ?? '' }} @if($school->school_id_code) &bull; School ID: {{ $school->school_id_code }} @endif</p>
</div>

<div class="title">Certificate of Recomputed Final Grade (Remedial Classes)</div>

<p class="intro">
    This is to certify that <strong>{{ $enrollment->student->formal_name }}</strong>
    (LRN: <strong>{{ $enrollment->student->lrn ?? 'N/A' }}</strong>, Student ID: <strong>{{ $enrollment->student->student_number }}</strong>),
    a learner of <strong>{{ $enrollment->gradeLevel->name }} &ndash; {{ $enrollment->section->name }}</strong>
    during School Year <strong>{{ $enrollment->schoolYear->name }}</strong> ({{ $enrollment->curriculum->name }}),
    attended and completed the required Remedial / Learning Recovery Class(es) in accordance with DepEd guidelines, with the following official recomputed final rating(s):
</p>

<table class="remedial">
    <thead>
    <tr>
        <th class="left">Learning Area</th>
        <th>Final Rating</th>
        <th>Remedial Class Mark</th>
        <th>Recomputed Final Grade</th>
        <th>Remarks</th>
        <th>Inclusive Dates</th>
    </tr>
    </thead>
    <tbody>
    @foreach($remedials as $rec)
        <tr>
            <td class="left"><strong>{{ $rec->subject->name }}</strong> ({{ $rec->subject->code }})</td>
            <td>{{ number_format($rec->final_rating, 0) }}</td>
            <td>{{ number_format($rec->remedial_class_mark, 0) }}</td>
            <td><strong>{{ number_format($rec->recomputed_final_grade, 0) }}</strong></td>
            <td class="{{ $rec->isPassed() ? 'passed' : 'failed' }}">{{ $rec->remarks }}</td>
            <td>
                @if($rec->conducted_from || $rec->conducted_to)
                    {{ $rec->conducted_from?->format('M d, Y') ?? '—' }} – {{ $rec->conducted_to?->format('M d, Y') ?? '—' }}
                @else
                    SY {{ $enrollment->schoolYear->name }}
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<p class="intro">
    Issued this <strong>{{ $issuedDate }}</strong> at {{ $school->school_name }} for SF10 / Form 137 permanent academic recording and promotion eligibility verification.
</p>

<table class="sigs">
    <tr>
        <td>
            <div class="sig-line">{{ $remedials->first()?->conducted_by ?? ($enrollment->section->adviser?->full_name ?? 'Remedial Class Teacher') }}</div>
            <div>Remedial Class Teacher / Adviser</div>
        </td>
        <td>
            <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
            <div>{{ $school->principal_title ?? 'School Principal' }}</div>
        </td>
    </tr>
</table>
</body>
</html>
