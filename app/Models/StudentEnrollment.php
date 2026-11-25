<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentEnrollment extends Model
{
    use HasFactory;

    public const STATUS_ENROLLED = 'enrolled';
    public const STATUS_TRANSFERRED_SECTION = 'transferred_section';
    public const STATUS_TRANSFERRED_OUT = 'transferred_out';
    public const STATUS_DROPPED = 'dropped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PROMOTED = 'promoted';
    public const STATUS_RETAINED = 'retained';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'grade_level_id',
        'section_id',
        'curriculum_id',
        'shs_track_id',
        'shs_strand_id',
        'enrollment_status',
        'enrolled_at',
        'transferred_from_enrollment_id',
        'transfer_reason',
        'is_current',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
        'is_current' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function shsTrack(): BelongsTo
    {
        return $this->belongsTo(ShsTrack::class);
    }

    public function shsStrand(): BelongsTo
    {
        return $this->belongsTo(ShsStrand::class);
    }

    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_enrollment_id');
    }

    public function quarterlyGrades(): HasMany
    {
        return $this->hasMany(QuarterlyGrade::class);
    }

    public function assessmentScores(): HasMany
    {
        return $this->hasMany(StudentAssessmentScore::class);
    }

    public function remedialClasses(): HasMany
    {
        return $this->hasMany(RemedialClass::class, 'student_enrollment_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
