<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAssessmentScore extends Model
{
    use HasFactory;

    public const STATUS_SCORED = 'scored';
    public const STATUS_MISSING = 'missing';
    public const STATUS_EXCUSED = 'excused';

    protected $fillable = [
        'assessment_id',
        'student_enrollment_id',
        'raw_score',
        'status',
        'remarks',
        'encoded_by',
    ];

    protected $casts = [
        'raw_score' => 'float',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}
