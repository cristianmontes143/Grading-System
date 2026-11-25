<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentIntervention extends Model
{
    public const CATEGORY_REMEDIATION = 'remediation';
    public const CATEGORY_PARENT_CONFERENCE = 'parent_conference';
    public const CATEGORY_COUNSELING = 'counseling';
    public const CATEGORY_COMMENDATION = 'commendation';

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'student_enrollment_id',
        'subject_id',
        'grading_period_id',
        'category',
        'notes',
        'action_plan',
        'follow_up_date',
        'status',
        'resolved_at',
        'recorded_by',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function gradingPeriod(): BelongsTo
    {
        return $this->belongsTo(GradingPeriod::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_REMEDIATION => 'Academic Remediation',
            self::CATEGORY_PARENT_CONFERENCE => 'Parent-Teacher Conference',
            self::CATEGORY_COUNSELING => 'Guidance / Counseling',
            self::CATEGORY_COMMENDATION => 'Academic Commendation',
        ];
    }
}
