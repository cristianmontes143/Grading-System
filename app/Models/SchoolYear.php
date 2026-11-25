<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
    use HasFactory;

    public const TERM_TRIMESTER = 'trimester';
    public const TERM_QUARTERLY = 'quarterly';
    public const TERM_SEMESTRAL = 'semestral';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'term_structure',
        'is_active',
        'is_closed',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_closed' => 'boolean',
    ];

    public function gradingPeriods(): HasMany
    {
        return $this->hasMany(GradingPeriod::class)->orderBy('sequence');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function curriculumImplementations(): HasMany
    {
        return $this->hasMany(CurriculumGradeImplementation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }
}
