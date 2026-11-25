<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_id',
        'grade_level_id',
        'shs_track_id',
        'shs_strand_id',
        'parent_subject_id',
        'code',
        'name',
        'description',
        'subject_category',
        'weight_group',
        'semester',
        'is_graded',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_graded' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_subject_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_subject_id')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
