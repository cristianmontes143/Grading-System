<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemedialClass extends Model
{
    use HasFactory;

    public const REMARKS_PASSED = 'PASSED';
    public const REMARKS_FAILED = 'FAILED';

    protected $fillable = [
        'student_enrollment_id',
        'subject_id',
        'final_rating',
        'remedial_class_mark',
        'recomputed_final_grade',
        'remarks',
        'conducted_from',
        'conducted_to',
        'conducted_by',
        'school_name',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'final_rating' => 'float',
        'remedial_class_mark' => 'float',
        'recomputed_final_grade' => 'float',
        'conducted_from' => 'date',
        'conducted_to' => 'date',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * DepEd DO 13, s. 2018 / SF10 formula:
     * Recomputed Final Grade = round((Final Rating + Remedial Class Mark) / 2)
     */
    public static function computeRecomputedGrade(float $finalRating, float $remedialMark): float
    {
        return (float) round(($finalRating + $remedialMark) / 2, 0);
    }

    public function isPassed(): bool
    {
        return $this->recomputed_final_grade >= 75.0;
    }
}
