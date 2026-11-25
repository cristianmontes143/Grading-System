<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Learner Progress Report Card</title>
    <style>
        @page { margin: 22px 26px; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            color: #1e293b;
            line-height: 1.35;
        }
        .card-page { page-break-after: always; }
        .card-page:last-child { page-break-after: auto; }
        .draft-banner {
            background: #fef2f2;
            border: 1.5px solid #dc2626;
            color: #991b1b;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
        }
        .header-box {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-box .deped-line { font-size: 8.5px; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; }
        .header-box .school-name { font-size: 14px; font-weight: bold; color: #0f172a; margin: 2px 0; }
        .header-box .doc-title { font-size: 11px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; margin-top: 2px; }
        .header-box .disclaimer { font-size: 7.5px; color: #64748b; margin-top: 2px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { padding: 3px 6px; border: 1px solid #cbd5e1; font-size: 8.8px; }
        .info-label { color: #475569; font-weight: bold; width: 16%; background: #f8fafc; }
        .section-title {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 4px 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 8px;
            margin-bottom: 0;
        }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .data-table th {
            background: #f1f5f9;
            border: 1px solid #94a3b8;
            padding: 4px 5px;
            font-size: 8.3px;
            text-align: center;
            color: #0f172a;
        }
        .data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 8.5px;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .pending-text { color: #b45309; font-weight: bold; font-size: 8px; }
        .failed-text { color: #b91c1c; font-weight: bold; }
        .summary-row td { background: #eff6ff; font-weight: bold; border-top: 1.5px solid #1e3a8a; }
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .sig-table td { width: 50%; text-align: center; padding: 0 20px; vertical-align: bottom; }
        .sig-line { border-top: 1px solid #334155; padding-top: 3px; font-weight: bold; font-size: 9px; }
        .sig-sub { font-size: 8px; color: #64748b; }
        .footer-note { margin-top: 10px; font-size: 7.5px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 5px; }
    </style>
</head>
<body>
@foreach($cards as $card)
    @php
        $school = $card['school'];
        $template = $card['template'];
        $enrollment = $card['enrollment'];
        $student = $enrollment->student;
        $periods = $card['periods'];
    @endphp
    <div class="card-page">
        @if($card['has_missing_grades'])
            <div class="draft-banner">
                UNOFFICIAL / INCOMPLETE DRAFT — PENDING SUBJECT GRADES:
                {{ collect($card['missing_subjects'])->pluck('subject_name')->implode(', ') }}
            </div>
        @endif

        {{-- School Header --}}
        <div class="header-box">
            <div class="deped-line">
                Republic of the Philippines &bull; Department of Education
                @if($school->region) &bull; {{ $school->region }} @endif
                @if($school->division) &bull; {{ $school->division }} @endif
            </div>
            <div class="school-name">{{ $school->school_name }}</div>
            @if($school->address)
                <div class="deped-line">{{ $school->address }} @if($school->school_id_code) | School ID: {{ $school->school_id_code }} @endif</div>
            @endif
            <div class="doc-title">{{ $template->header_subtitle }}</div>
            <div class="disclaimer">
                {{ $template->disclaimer_label }} &bull; SY {{ $enrollment->schoolYear->name }}
                &bull; Curriculum: {{ $enrollment->curriculum->name }} ({{ $enrollment->curriculum->code }})
            </div>
        </div>

        {{-- Learner Information --}}
        <table class="info-table">
            <tr>
                <td class="info-label">Learner Name</td>
                <td class="font-bold" style="width: 38%;">{{ $student->formal_name }}</td>
                <td class="info-label">Student ID</td>
                <td>{{ $student->student_number }}</td>
            </tr>
            <tr>
                <td class="info-label">Grade &amp; Section</td>
                <td>
                    {{ $enrollment->gradeLevel->name }} – {{ $enrollment->section->name }}
                    @if($enrollment->shsTrack) ({{ $enrollment->shsTrack->code }}{{ $enrollment->shsStrand ? ' / ' . $enrollment->shsStrand->code : '' }}) @endif
                </td>
                @if($template->show_lrn)
                    <td class="info-label">LRN</td>
                    <td>{{ $student->lrn ?? 'Not Assigned' }}</td>
                @else
                    <td class="info-label">School Year</td>
                    <td>{{ $enrollment->schoolYear->name }}</td>
                @endif
            </tr>
            <tr>
                <td class="info-label">Sex / Birthdate</td>
                <td>{{ $student->gender }} &bull; {{ $student->date_of_birth->format('M d, Y') }}</td>
                <td class="info-label">Class Adviser</td>
                <td>{{ $enrollment->section->adviser?->full_name ?? 'Unassigned' }}</td>
            </tr>
        </table>

        {{-- Learning Progress & Achievement --}}
        <div class="section-title">Report on Learning Progress and Achievement</div>
        <table class="data-table">
            <thead>
            <tr>
                <th class="text-left" style="width: 38%;">Learning Areas</th>
                @foreach($periods as $p)
                    <th>{{ $p->code }}</th>
                @endforeach
                <th>Final Grade</th>
                <th style="width: 22%;">Remarks</th>
            </tr>
            </thead>
            <tbody>
            @foreach($card['subject_rows'] as $row)
                <tr>
                    <td class="font-bold">{{ $row['subject']->name }}</td>
                    @foreach($periods as $p)
                        @php $val = $row['period_grades'][$p->id] ?? null; @endphp
                        <td class="text-center">
                            @if($val !== null)
                                <span class="{{ is_numeric($val) && (float) $val < 75 ? 'failed-text' : '' }}">{{ $val }}</span>
                            @else
                                <span class="pending-text">Pending</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-center font-bold">
                        @if($row['final_grade'] !== null)
                            <span class="{{ $row['final_grade'] < 75 ? 'failed-text' : '' }}">{{ number_format($row['final_grade'], 0) }}</span>
                        @elseif($row['is_missing'])
                            <span class="pending-text">Pending</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center">{{ $row['remarks'] }}</td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td colspan="{{ $periods->count() + 1 }}" class="text-left">GENERAL AVERAGE &amp; PROMOTION STATUS</td>
                <td class="text-center">
                    {{ $card['general_average'] !== null ? number_format($card['general_average'], 0) : ($card['is_descriptive_only'] ? 'Descriptive' : 'Incomplete') }}
                </td>
                <td class="text-center" style="text-transform: uppercase;">
                    {{ str_replace('_', ' ', $card['promotion_status']) }}
                </td>
            </tr>
            </tbody>
        </table>

        {{-- Observed Core Values (when enabled) --}}
        @if($school->observed_values_enabled && $template->show_observed_values)
            <div class="section-title">Report on Learner's Observed Values (AO = Always, SO = Sometimes, RO = Rarely, NO = Not Observed)</div>
            <table class="data-table">
                <thead>
                <tr>
                    <th class="text-left" style="width: 18%;">Core Value</th>
                    <th class="text-left">Behavior Statement</th>
                    @foreach($periods as $p)
                        <th style="width: 7%;">{{ $p->code }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($card['core_value_statements'] as $code => $meta)
                    <tr>
                        <td class="font-bold">{{ $meta['core_label'] }}</td>
                        <td>{{ $meta['statement'] }}</td>
                        @foreach($periods as $p)
                            @php $obs = $card['observed_values']->get($code . ':' . $p->id); @endphp
                            <td class="text-center font-bold">{{ $obs?->rating ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        {{-- Attendance Summary (when enabled) --}}
        @if($school->attendance_enabled && $template->show_attendance)
            <div class="section-title">Report on Attendance</div>
            <table class="data-table">
                <thead>
                <tr>
                    <th class="text-left">Metric</th>
                    @foreach($card['attendance'] as $att)
                        <th>{{ \Carbon\Carbon::create($att->year_number, $att->month_number, 1)->format('M') }}</th>
                    @endforeach
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td class="font-bold">No. of School Days</td>
                    @foreach($card['attendance'] as $att)
                        <td class="text-center">{{ $att->school_days }}</td>
                    @endforeach
                    <td class="text-center font-bold">{{ $card['attendance_totals']['school_days'] }}</td>
                </tr>
                <tr>
                    <td class="font-bold">No. of Days Present</td>
                    @foreach($card['attendance'] as $att)
                        <td class="text-center">{{ $att->days_present }}</td>
                    @endforeach
                    <td class="text-center font-bold">{{ $card['attendance_totals']['days_present'] }}</td>
                </tr>
                <tr>
                    <td class="font-bold">No. of Days Absent</td>
                    @foreach($card['attendance'] as $att)
                        <td class="text-center">{{ $att->days_absent }}</td>
                    @endforeach
                    <td class="text-center font-bold">{{ $card['attendance_totals']['days_absent'] }}</td>
                </tr>
                <tr>
                    <td class="font-bold">No. of Times Tardy</td>
                    @foreach($card['attendance'] as $att)
                        <td class="text-center">{{ $att->days_tardy }}</td>
                    @endforeach
                    <td class="text-center font-bold">{{ $card['attendance_totals']['days_tardy'] }}</td>
                </tr>
                </tbody>
            </table>
        @endif

        {{-- Signatories --}}
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-line">{{ $enrollment->section->adviser?->full_name ?? 'Class Adviser' }}</div>
                    <div class="sig-sub">Class Adviser</div>
                </td>
                <td>
                    <div class="sig-line">{{ $school->principal_name ?? 'School Principal / Head' }}</div>
                    <div class="sig-sub">{{ $school->principal_title }}</div>
                </td>
            </tr>
        </table>

        <div class="footer-note">
            Generated on {{ now()->format('F d, Y h:i A') }}
            @if($template->footer_note) &bull; {{ $template->footer_note }} @endif
        </div>
    </div>
@endforeach
</body>
</html>
