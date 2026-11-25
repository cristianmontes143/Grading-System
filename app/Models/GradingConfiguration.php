<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingConfiguration extends Model
{
    use HasFactory;

    public const MODE_NUMERICAL_TRANSMUTED = 'numerical_transmuted';
    public const MODE_NUMERICAL_ZERO_BASED = 'numerical_zero_based';
    public const MODE_DESCRIPTIVE = 'descriptive';

    protected $fillable = [
        'school_year_id',
        'curriculum_id',
        'grade_level_id',
        'subject_id',
        'weight_group',
        'name',
        'grading_mode',
        'transmutation_table_id',
        'passing_grade',
        'rounding_precision',
        'descriptive_scale_json',
        'remarks_scale_json',
        'policy_reference_id',
        'is_confirmed_by_admin',
        'confirmed_by',
        'confirmed_at',
        'is_active',
    ];

    protected $casts = [
        'passing_grade' => 'float',
        'rounding_precision' => 'integer',
        'descriptive_scale_json' => 'array',
        'remarks_scale_json' => 'array',
        'is_confirmed_by_admin' => 'boolean',
        'confirmed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function transmutationTable(): BelongsTo
    {
        return $this->belongsTo(TransmutationTable::class);
    }

    public function policyReference(): BelongsTo
    {
        return $this->belongsTo(PolicyReference::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function components(): HasMany
    {
        return $this->hasMany(GradingComponent::class)->orderBy('sort_order')->orderBy('id');
    }

    public function topLevelComponents(): HasMany
    {
        return $this->hasMany(GradingComponent::class)
            ->whereNull('parent_component_id')
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isDescriptive(): bool
    {
        return $this->grading_mode === self::MODE_DESCRIPTIVE;
    }

    public function usesTransmutation(): bool
    {
        return $this->grading_mode === self::MODE_NUMERICAL_TRANSMUTED;
    }
}
