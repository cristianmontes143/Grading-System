<?php

namespace App\Services\Reports;

use App\Models\GradingPeriod;
use App\Models\SchoolSetting;
use App\Models\StudentEnrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class RegistrarCertificateService
{
    public const TYPE_ENROLLMENT = 'enrollment';
    public const TYPE_GOOD_MORAL = 'good_moral';
    public const TYPE_GWA_RANKING = 'gwa_ranking';

    public function __construct(
        private readonly SectionSummaryService $sectionSummaryService,
        private readonly ReportCardAssemblyService $assemblyService
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ENROLLMENT => 'Certificate of Enrollment',
            self::TYPE_GOOD_MORAL => 'Certificate of Good Moral Character',
            self::TYPE_GWA_RANKING => 'Certificate of General Weighted Average (GWA) & Ranking',
        ];
    }

    public function generatePdf(
        StudentEnrollment $enrollment,
        string $type,
        ?string $purpose = null,
        ?GradingPeriod $period = null,
        ?string $controlNumber = null
    ): Response {
        $enrollment->loadMissing([
            'student',
            'schoolYear.gradingPeriods',
            'gradeLevel',
            'section.adviser',
            'curriculum',
            'shsTrack',
            'shsStrand',
        ]);

        $school = SchoolSetting::current();
        $logoDataUri = $this->resolveLogoDataUri($school);

        $academicMetrics = null;
        if ($type === self::TYPE_GWA_RANKING) {
            $assembled = $this->assemblyService->assemble($enrollment, $period);
            $rankInfo = null;

            $targetPeriod = $period ?? $enrollment->schoolYear->gradingPeriods->last();
            if ($targetPeriod) {
                $secSummary = $this->sectionSummaryService->buildSectionSummary($enrollment->section, $targetPeriod);
                $learnerRow = collect($secSummary['learner_rows'])->first(
                    fn (array $row) => $row['enrollment']->id === $enrollment->id
                );

                if ($learnerRow) {
                    $rankInfo = [
                        'rank' => $learnerRow['rank'] ?? null,
                        'section_total' => count($secSummary['learner_rows']),
                        'honor' => $learnerRow['honor'] ?? null,
                        'period_average' => $learnerRow['average'] ?? null,
                        'period_name' => $targetPeriod->name,
                    ];
                }
            }

            $academicMetrics = [
                'general_average' => $assembled['general_average'],
                'promotion_status' => $assembled['promotion_status'],
                'subject_count' => count($assembled['subject_rows']),
                'rank_info' => $rankInfo,
            ];
        }

        $resolvedControlNo = $controlNumber ?: sprintf(
            'REG-%s-%04d',
            now()->format('Ymd'),
            $enrollment->id
        );

        $pdf = Pdf::loadView('reports.registrar-certificate-pdf', [
            'school' => $school,
            'logoDataUri' => $logoDataUri,
            'enrollment' => $enrollment,
            'student' => $enrollment->student,
            'type' => $type,
            'typeTitle' => self::types()[$type] ?? 'Official Certification',
            'purpose' => trim((string) $purpose) !== '' ? trim((string) $purpose) : 'whatever legal and academic purpose it may serve',
            'controlNumber' => $resolvedControlNo,
            'issuedDate' => now()->format('F d, Y'),
            'academicMetrics' => $academicMetrics,
        ])->setPaper('a4', 'portrait');

        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', $enrollment->student->student_number);
        $safeType = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtoupper($type));

        return $pdf->stream("Registrar_{$safeType}_{$safeId}.pdf");
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
