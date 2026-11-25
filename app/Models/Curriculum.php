<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    use HasFactory;

    protected $table = 'curricula';

    protected $fillable = [
        'code',
        'name',
        'version',
        'education_stage',
        'policy_reference_id',
        'status',
        'description',
    ];

    public function policyReference(): BelongsTo
    {
        return $this->belongsTo(PolicyReference::class);
    }

    public function gradeImplementations(): HasMany
    {
        return $this->hasMany(CurriculumGradeImplementation::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function shsTracks(): HasMany
    {
        return $this->hasMany(ShsTrack::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'transitioning']);
    }
}
