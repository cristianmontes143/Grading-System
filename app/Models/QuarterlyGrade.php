<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterlyGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_enrollment_id',
        'subject_id',
        'teacher_assignment_id',
        'grading_period_id',
        'grading_configuration_id',
        'component_breakdown_json',
        'initial_grade',
        'transmuted_grade',
        'final_grade',
        'descriptive_rating',
        'remarks',
        'missing_scores_count',
        'is_official',
        'is_locked',
        'computed_at',
    ];

    protected $casts = [
        'component_breakdown_json' => 'array',
        'initial_grade' => 'float',
        'transmuted_grade' => 'float',
        'final_grade' => 'float',
        'missing_scores_count' => 'integer',
        'is_official' => 'boolean',
        'is_locked' => 'boolean',
        'computed_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function gradingPeriod(): BelongsTo
    {
        return $this->belongsTo(GradingPeriod::class);
    }

    public function gradingConfiguration(): BelongsTo
    {
        return $this->belongsTo(GradingConfiguration::class);
    }

    public function getDisplayGradeAttribute(): string
    {
        if ($this->descriptive_rating !== null) {
            return $this->descriptive_rating;
        }

        if ($this->final_grade !== null) {
            return number_format($this->final_grade, $this->final_grade == floor($this->final_grade) ? 0 : 2);
        }

        return '—';
    }
}
