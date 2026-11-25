<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $typeTitle }} — {{ $student->formal_name }}</title>
    <style>
        body { font-family: DejaVu Sans, Georgia, serif; color: #0f172a; margin: 0; padding: 34px 44px; font-size: 11.5px; }
        .frame { border: 3px double #1e3a8a; padding: 28px 34px; min-height: 920px; position: relative; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 22px; }
        .header img { height: 56px; margin-bottom: 6px; }
        .header p { margin: 1px 0; font-size: 10px; color: #334155; }
        .header h1 { margin: 4px 0 2px; font-size: 16px; text-transform: uppercase; letter-spacing: 0.6px; color: #1e3a8a; }
        .meta { text-align: right; font-size: 9.5px; color: #475569; margin-bottom: 18px; }
        .cert-title { text-align: center; font-size: 17px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: #1e3a8a; margin: 18px 0 22px; }
        .salutation { font-weight: bold; margin-bottom: 16px; letter-spacing: 0.4px; }
        .body-p { text-align: justify; line-height: 1.85; text-indent: 34px; margin-bottom: 16px; font-size: 11.5px; }
        .metrics-box { border: 1px solid #cbd5e1; background: #f8fafc; padding: 12px 16px; margin: 18px 0; }
        .metrics-box table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .metrics-box td { padding: 4px 6px; }
        .sigs { width: 100%; margin-top: 64px; border-collapse: collapse; }
        .sigs td { width: 50%; text-align: center; vertical-align: bottom; padding-top: 28px; }
        .sig-line { border-top: 1px solid #0f172a; width: 78%; margin: 0 auto 4px; padding-top: 4px; font-weight: bold; font-size: 11px; text-transform: uppercase; }
        .sig-role { font-size: 9.5px; color: #475569; }
        .seal-note { position: absolute; bottom: 20px; left: 34px; right: 34px; border-top: 1px dashed #cbd5e1; padding-top: 8px; font-size: 8.5px; color: #64748b; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
<div class="frame">
    <div class="header">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="School Logo"><br>
        @endif
        <p>Republic of the Philippines</p>
        <p><strong>Department of Education</strong></p>
        <p>{{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }}</p>
        <h1>{{ $school->school_name }}</h1>
        <p>{{ $school->address ?? '' }} @if($school->school_id_code) &bull; School ID: {{ $school->school_id_code }} @endif</p>
    </div>

    <div class="meta">
        <strong>Control No.:</strong> {{ $controlNumber }}<br>
        <strong>Date Issued:</strong> {{ $issuedDate }}
    </div>

    <div class="cert-title">{{ $typeTitle }}</div>

    <p class="salutation">TO WHOM IT MAY CONCERN:</p>

    @if($type === 'enrollment')
        <p class="body-p">
            This is to certify that <strong>{{ strtoupper($student->formal_name) }}</strong>,
            bearing Learner Reference Number (LRN) <strong>{{ $student->lrn ?? 'N/A' }}</strong>
            and Student ID <strong>{{ $student->student_number }}</strong>, is officially enrolled as a
            <strong>{{ $enrollment->gradeLevel->name }}</strong> learner in section
            <strong>{{ $enrollment->section->name }}</strong>
            @if($enrollment->shsTrack)
                under the <strong>{{ $enrollment->shsTrack->name }}</strong>
                @if($enrollment->shsStrand) ({{ $enrollment->shsStrand->name }}) @endif
            @endif
            for School Year <strong>{{ $enrollment->schoolYear->name }}</strong>
            ({{ $enrollment->curriculum->name }}) at <strong>{{ $school->school_name }}</strong>.
        </p>
        <p class="body-p">
            Based on official registrar records, the learner's current enrollment status is
            <strong>{{ ucwords(str_replace('_', ' ', $enrollment->enrollment_status)) }}</strong>
            under the advisory supervision of <strong>{{ $enrollment->section->adviser?->full_name ?? 'Assigned Class Adviser' }}</strong>.
        </p>
    @elseif($type === 'good_moral')
        <p class="body-p">
            This is to certify that <strong>{{ strtoupper($student->formal_name) }}</strong>
            (LRN: <strong>{{ $student->lrn ?? 'N/A' }}</strong>), a learner of
            <strong>{{ $enrollment->gradeLevel->name }} &ndash; {{ $enrollment->section->name }}</strong>
            during School Year <strong>{{ $enrollment->schoolYear->name }}</strong> at
            <strong>{{ $school->school_name }}</strong>, is known to be of
            <strong>Good Moral Character</strong> and law-abiding conduct.
        </p>
        <p class="body-p">
            As of the date of issuance, the learner has no pending disciplinary case, derogatory record, or unsettled school accountability on file with the Office of the Registrar and Guidance Services.
        </p>
    @else
        <p class="body-p">
            This is to certify that <strong>{{ strtoupper($student->formal_name) }}</strong>
            (LRN: <strong>{{ $student->lrn ?? 'N/A' }}</strong>), a learner of
            <strong>{{ $enrollment->gradeLevel->name }} &ndash; {{ $enrollment->section->name }}</strong>
            during School Year <strong>{{ $enrollment->schoolYear->name }}</strong> ({{ $enrollment->curriculum->name }}),
            has achieved the following official academic standing based on submitted and verified scholastic records:
        </p>
        <div class="metrics-box">
            <table>
                <tr>
                    <td><strong>General Weighted Average (GWA):</strong></td>
                    <td>
                        @if(($academicMetrics['general_average'] ?? null) !== null)
                            <strong>{{ number_format((float) $academicMetrics['general_average'], 2) }}</strong>
                            (Rounded: {{ number_format((float) $academicMetrics['general_average'], 0) }})
                        @elseif(($academicMetrics['rank_info']['period_average'] ?? null) !== null)
                            <strong>{{ number_format((float) $academicMetrics['rank_info']['period_average'], 2) }}</strong>
                            ({{ $academicMetrics['rank_info']['period_name'] }})
                        @else
                            In Progress / Incomplete
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>Section Academic Standing:</strong></td>
                    <td>
                        @if(($academicMetrics['rank_info']['rank'] ?? null) !== null)
                            Rank <strong>#{{ $academicMetrics['rank_info']['rank'] }}</strong> out of {{ $academicMetrics['rank_info']['section_total'] }} learners
                            ({{ $academicMetrics['rank_info']['period_name'] }})
                        @else
                            Unranked / Pending Completion
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><strong>DepEd Honor Distinction (DO 36, s. 2016):</strong></td>
                    <td><strong>{{ $academicMetrics['rank_info']['honor'] ?? 'None / Regular Standing' }}</strong></td>
                </tr>
            </table>
        </div>
    @endif

    <p class="body-p">
        This certification is issued upon the request of the learner/parent/guardian on <strong>{{ $issuedDate }}</strong>
        at {{ $school->school_name }} for <strong>{{ $purpose }}</strong>.
    </p>

    <table class="sigs">
        <tr>
            <td>
                <div class="sig-line">Office of the School Registrar</div>
                <div class="sig-role">Verified &amp; Certified Correct</div>
            </td>
            <td>
                <div class="sig-line">{{ $school->principal_name ?? 'School Principal' }}</div>
                <div class="sig-role">{{ $school->principal_title ?? 'School Principal' }}</div>
            </td>
        </tr>
    </table>

    <div class="seal-note">
        <span>Not valid without official school dry seal. &bull; Control No.: {{ $controlNumber }}</span>
    </div>
</div>
</body>
</html>
