<?php

namespace App\Services\Reports;

use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentEnrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class CertificatePdfService
{
    public function __construct(private readonly SectionSummaryService $sectionSummaryService)
    {
    }

    /**
     * Generate a batch landscape PDF containing official DepEd DO 36, s. 2016
     * Certificates of Recognition for all honor qualifiers in a section + period.
     */
    public function generateSectionHonorCertificates(Section $section, GradingPeriod $period): ?Response
    {
        $summary = $this->sectionSummaryService->buildSectionSummary($section, $period);
        $honors = $summary['honor_roll'];

        if ($honors->isEmpty()) {
            return null;
        }

        $school = SchoolSetting::current();
        $logoDataUri = $this->resolveLogoDataUri($school);

        $pdf = Pdf::loadView('reports.certificates-pdf', [
            'school' => $school,
            'logoDataUri' => $logoDataUri,
            'section' => $summary['section'],
            'period' => $summary['period'],
            'honors' => $honors,
            'issuedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'landscape');

        $safeSec = preg_replace('/[^A-Za-z0-9_-]+/', '_', $section->gradeLevel->code . '_' . $section->name);

        return $pdf->stream("Honor_Certificates_{$safeSec}_{$period->code}.pdf");
    }

    /**
     * Generate an individual landscape PDF Certificate of Recognition for one qualifying learner.
     */
    public function generateIndividualHonorCertificate(StudentEnrollment $enrollment, GradingPeriod $period): ?Response
    {
        $enrollment->loadMissing(['section.gradeLevel', 'section.schoolYear', 'section.adviser', 'student']);
        $summary = $this->sectionSummaryService->buildSectionSummary($enrollment->section, $period);

        $match = $summary['honor_roll']->firstWhere('enrollment.id', $enrollment->id);
        if (! $match) {
            return null;
        }

        $school = SchoolSetting::current();
        $logoDataUri = $this->resolveLogoDataUri($school);

        $pdf = Pdf::loadView('reports.certificates-pdf', [
            'school' => $school,
            'logoDataUri' => $logoDataUri,
            'section' => $summary['section'],
            'period' => $summary['period'],
            'honors' => collect([$match]),
            'issuedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'landscape');

        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', $enrollment->student->student_number);

        return $pdf->stream("Honor_Certificate_{$safeId}_{$period->code}.pdf");
    }

    private function resolveLogoDataUri(SchoolSetting $school): ?string
    {
        if (! $school->logo_path) {
            return null;
        }

        $path = storage_path('app/public/' . $school->logo_path);
        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
