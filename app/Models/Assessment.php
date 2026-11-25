<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_assignment_id',
        'grading_period_id',
        'grading_component_id',
        'title',
        'assessment_type',
        'max_score',
        'assessment_date',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'max_score' => 'float',
        'assessment_date' => 'date',
        'is_published' => 'boolean',
    ];

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function gradingPeriod(): BelongsTo
    {
        return $this->belongsTo(GradingPeriod::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradingComponent::class, 'grading_component_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(StudentAssessmentScore::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
