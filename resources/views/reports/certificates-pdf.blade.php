<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Academic Recognition</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Serif, Georgia, serif; color: #0f172a; margin: 0; padding: 0; }
        .page {
            page-break-after: always;
            border: 8px double #1e3a8a;
            padding: 16px;
            height: 675px;
            box-sizing: border-box;
            position: relative;
            background: #ffffff;
        }
        .page:last-child { page-break-after: auto; }
        .inner-frame {
            border: 2px solid #d97706;
            height: 100%;
            box-sizing: border-box;
            padding: 26px 46px;
            text-align: center;
            position: relative;
        }
        .header-small { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; color: #475569; margin: 0; }
        .school-name { font-family: DejaVu Sans, Arial, sans-serif; font-size: 18px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; margin: 4px 0 2px; }
        .school-sub { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #64748b; margin: 0 0 18px; }
        .cert-title { font-size: 32px; font-weight: bold; color: #1e3a8a; letter-spacing: 2px; text-transform: uppercase; margin: 10px 0 4px; }
        .cert-subtitle { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #b45309; font-weight: bold; margin: 0 0 18px; }
        .presented { font-size: 13px; color: #475569; font-style: italic; margin: 0 0 10px; }
        .learner-name { font-size: 30px; font-weight: bold; color: #0f172a; border-bottom: 2px solid #cbd5e1; display: inline-block; padding: 0 28px 6px; margin: 0 0 16px; }
        .citation { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #334155; line-height: 1.7; max-width: 660px; margin: 0 auto 14px; }
        .honor-ribbon {
            display: inline-block;
            font-family: DejaVu Sans, Arial, sans-serif;
            background: #1e3a8a;
            color: #fde68a;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 8px 28px;
            border-radius: 4px;
            border: 1.5px solid #d97706;
            margin: 4px 0 14px;
        }
        .meta-line { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #475569; margin: 0 0 28px; }
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 22px; }
        .sig-table td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 28px; }
        .sig-name { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; font-weight: bold; color: #0f172a; border-top: 1px solid #334155; padding-top: 5px; width: 240px; margin: 0 auto 2px; text-transform: uppercase; }
        .sig-role { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #64748b; }
        .logo { height: 52px; margin-bottom: 6px; }
    </style>
</head>
<body>
@foreach($honors as $hr)
    <div class="page">
        <div class="inner-frame">
            @if($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="School Logo" class="logo">
            @endif
            <p class="header-small">Republic of the Philippines &bull; Department of Education</p>
            <div class="school-name">{{ $school->school_name }}</div>
            <p class="school-sub">
                {{ collect([$school->region, $school->division, $school->district])->filter()->implode(' • ') }}
                @if($school->school_id_code) &bull; School ID: {{ $school->school_id_code }} @endif
            </p>

            <div class="cert-title">Certificate of Recognition</div>
            <div class="cert-subtitle">Academic Excellence Award &bull; DepEd Order No. 36, s. 2016</div>

            <p class="presented">is proudly presented to</p>

            <div class="learner-name">{{ $hr['student']->full_name }}</div>

            <div class="citation">
                of <strong>{{ $section->gradeLevel->name }} &ndash; {{ $section->name }}</strong>
                for demonstrating exemplary academic performance during the
                <strong>{{ $period->name }} ({{ $period->code }})</strong> of
                <strong>School Year {{ $section->schoolYear->name }}</strong>,
                earning a General Average of <strong>{{ number_format((float) $hr['average'], 2) }}</strong>
                and achieving the official distinction of
            </div>

            <div class="honor-ribbon">{{ $hr['honor'] }}</div>

            <p class="meta-line">
                Given this {{ $issuedDate }} at {{ $school->address ?: $school->school_name }}.
            </p>

            <table class="sig-table">
                <tr>
                    <td>
                        <div class="sig-name">{{ $section->adviser?->full_name ?? 'Class Adviser' }}</div>
                        <div class="sig-role">Class Adviser</div>
                    </td>
                    <td>
                        <div class="sig-name">{{ $school->principal_name ?? 'School Principal' }}</div>
                        <div class="sig-role">{{ $school->principal_title ?? 'School Principal' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
@endforeach
</body>
</html>
