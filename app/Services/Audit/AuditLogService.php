<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function record(
        string $eventType,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $actor = null,
        ?Request $request = null,
        array $context = []
    ): AuditLog {
        $req = $request ?? request();
        $user = $actor ?? Auth::user();

        return AuditLog::create([
            'event_type' => $eventType,
            'actor_user_id' => $user?->id,
            'teacher_id' => $context['teacher_id'] ?? null,
            'student_id' => $context['student_id'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'auditable_type' => $auditable ? $auditable::class : ($context['auditable_type'] ?? null),
            'auditable_id' => $auditable?->getKey() ?? ($context['auditable_id'] ?? null),
            'previous_grade' => isset($context['previous_grade']) ? (string) $context['previous_grade'] : null,
            'updated_grade' => isset($context['updated_grade']) ? (string) $context['updated_grade'] : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function recordGradeChange(
        string $eventType,
        Model $auditable,
        ?int $teacherId,
        ?int $studentId,
        ?int $subjectId,
        string|int|float|null $previousGrade,
        string|int|float|null $updatedGrade,
        ?string $reason = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null
    ): AuditLog {
        return $this->record(
            eventType: $eventType,
            auditable: $auditable,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
            actor: $actor,
            context: [
                'teacher_id' => $teacherId,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'previous_grade' => $previousGrade,
                'updated_grade' => $updatedGrade,
            ]
        );
    }
}
