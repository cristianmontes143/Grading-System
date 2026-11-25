<?php

namespace App\Services\Reports;

use App\Models\GradingPeriod;
use App\Models\ReportCard;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Validation\ValidationException;

class ReportCardPdfService
{
    public function __construct(
        private readonly ReportCardAssemblyService $assembler,
        private readonly AuditLogService $auditLog
    ) {
    }

    public function generateIndividualPdf(
        StudentEnrollment $enrollment,
        ?GradingPeriod $period,
        User $actor,
        bool $allowIncompleteDraft = false
    ): DomPdfInstance {
        $data = $this->assembler->assemble($enrollment, $period);

        if ($data['has_missing_grades'] && ! $allowIncompleteDraft) {
            $missingNames = collect($data['missing_subjects'])->pluck('subject_name')->implode(', ');
            throw ValidationException::withMessages([
                'report_card' => "Cannot issue an official report card for {$enrollment->student->formal_name} while required grades are pending ({$missingNames}). Use Preview Draft to view incomplete records.",
            ]);
        }

        $isOfficial = ! $data['has_missing_grades'];
        $data['is_official'] = $isOfficial;

        $record = ReportCard::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'school_year_id' => $enrollment->school_year_id,
            'grading_period_id' => $period?->id,
            'curriculum_id' => $enrollment->curriculum_id,
            'template_id' => $data['template']->id ?: null,
            'general_average' => $data['general_average'],
            'promotion_status' => $data['promotion_status'],
            'is_official' => $isOfficial,
            'has_missing_grades' => $data['has_missing_grades'],
            'missing_subjects_json' => $data['missing_subjects'],
            'snapshot_json' => [
                'curriculum_code' => $enrollment->curriculum->code,
                'general_average' => $data['general_average'],
                'promotion_status' => $data['promotion_status'],
                'subjects_count' => count($data['subject_rows']),
            ],
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);

        $this->auditLog->record(
            eventType: $isOfficial ? 'report_card.official_generated' : 'report_card.draft_generated',
            auditable: $record,
            newValues: [
                'student_id' => $enrollment->student_id,
                'curriculum_id' => $enrollment->curriculum_id,
                'is_official' => $isOfficial,
                'general_average' => $data['general_average'],
            ],
            actor: $actor,
            context: ['student_id' => $enrollment->student_id]
        );

        return Pdf::loadView('reports.report-card-pdf', ['cards' => [$data]])
            ->setPaper($data['template']->paper_size ?? 'a4', $data['template']->orientation ?? 'portrait');
    }

    public function generateSectionBatchPdf(
        Section $section,
        ?GradingPeriod $period,
        User $actor,
        bool $allowIncompleteDraft = false
    ): DomPdfInstance {
        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where('school_year_id', $section->school_year_id)
            ->where('is_current', true)
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
            ->values();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'report_card' => 'No students are enrolled in this section.',
            ]);
        }

        $cards = [];
        $incompleteCount = 0;

        foreach ($enrollments as $enr) {
            $data = $this->assembler->assemble($enr, $period);
            if ($data['has_missing_grades']) {
                $incompleteCount++;
            }
            $data['is_official'] = ! $data['has_missing_grades'];
            $cards[] = $data;
        }

        if ($incompleteCount > 0 && ! $allowIncompleteDraft) {
            throw ValidationException::withMessages([
                'report_card' => "Cannot issue official batch report cards: {$incompleteCount} learner(s) still have pending/unsubmitted subject grades. Use Batch Draft Preview to inspect incomplete cards.",
            ]);
        }

        foreach ($cards as $card) {
            $enr = $card['enrollment'];
            ReportCard::query()->create([
                'student_enrollment_id' => $enr->id,
                'school_year_id' => $enr->school_year_id,
                'grading_period_id' => $period?->id,
                'curriculum_id' => $enr->curriculum_id,
                'template_id' => $card['template']->id ?: null,
                'general_average' => $card['general_average'],
                'promotion_status' => $card['promotion_status'],
                'is_official' => $card['is_official'],
                'has_missing_grades' => $card['has_missing_grades'],
                'missing_subjects_json' => $card['missing_subjects'],
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);
        }

        $this->auditLog->record(
            eventType: $incompleteCount === 0 ? 'report_card.batch_official_generated' : 'report_card.batch_draft_generated',
            auditable: $section,
            newValues: [
                'section_id' => $section->id,
                'cards_count' => count($cards),
                'incomplete_count' => $incompleteCount,
            ],
            actor: $actor
        );

        $template = $cards[0]['template'];

        return Pdf::loadView('reports.report-card-pdf', ['cards' => $cards])
            ->setPaper($template->paper_size ?? 'a4', $template->orientation ?? 'portrait');
    }
}
