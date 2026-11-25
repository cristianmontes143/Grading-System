<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_year_id',
        'grade_level_id',
        'shs_track_id',
        'shs_strand_id',
        'adviser_id',
        'name',
        'room',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function shsTrack(): BelongsTo
    {
        return $this->belongsTo(ShsTrack::class);
    }

    public function shsStrand(): BelongsTo
    {
        return $this->belongsTo(ShsStrand::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'adviser_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function activeTeacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class)->where('is_active', true);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function currentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->where('is_current', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFullLabelAttribute(): string
    {
        $grade = $this->relationLoaded('gradeLevel') ? $this->gradeLevel?->name : null;

        return $grade ? "{$grade} – {$this->name}" : $this->name;
    }
}
