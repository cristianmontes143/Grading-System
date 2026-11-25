<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_enrollment_id',
        'school_year_id',
        'grading_period_id',
        'curriculum_id',
        'template_id',
        'general_average',
        'promotion_status',
        'is_official',
        'has_missing_grades',
        'missing_subjects_json',
        'snapshot_json',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'general_average' => 'float',
        'is_official' => 'boolean',
        'has_missing_grades' => 'boolean',
        'missing_subjects_json' => 'array',
        'snapshot_json' => 'array',
        'generated_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradingPeriod(): BelongsTo
    {
        return $this->belongsTo(GradingPeriod::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'template_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
