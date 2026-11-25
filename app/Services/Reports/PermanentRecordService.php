<?php

namespace App\Services\Reports;

use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PermanentRecordService
{
    public function __construct(private readonly ReportCardAssemblyService $assemblyService)
    {
    }

    /**
     * Assemble a learner's multi-school-year SF10 / Form 137 Permanent Academic Record.
     * Strictly preserves each enrollment's historical curriculum snapshot and ONLY includes
     * finalized/submitted official grades.
     *
     * @return array<string, mixed>
     */
    public function assembleForStudent(Student $student): array
    {
        $enrollments = StudentEnrollment::query()
            ->with([
                'schoolYear.gradingPeriods',
                'gradeLevel',
                'section.adviser',
                'curriculum.policyReference',
                'shsTrack',
                'shsStrand',
                'remedialClasses.subject',
            ])
            ->where('student_id', $student->id)
            ->orderBy('school_year_id')
            ->orderBy('enrolled_at')
            ->orderBy('id')
            ->get();

        $records = $enrollments->map(function (StudentEnrollment $enrollment) {
            $assembled = $this->assemblyService->assemble($enrollment);

            return [
                'enrollment' => $enrollment,
                'summary' => $assembled,
            ];
        });

        return [
            'school' => SchoolSetting::current(),
            'student' => $student,
            'records' => $records,
            'generated_at' => now(),
        ];
    }

    public function generatePdf(Student $student): Response
    {
        $data = $this->assembleForStudent($student);

        $pdf = Pdf::loadView('reports.sf10-pdf', $data)
            ->setPaper('a4', 'portrait');

        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', $student->student_number);

        return $pdf->stream("SF10_Permanent_Record_{$safeId}.pdf");
    }
}
