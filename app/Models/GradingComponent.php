<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'grading_configuration_id',
        'parent_component_id',
        'code',
        'name',
        'weight_percentage',
        'sort_order',
    ];

    protected $casts = [
        'weight_percentage' => 'float',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(GradingConfiguration::class, 'grading_configuration_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_component_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_component_id')->orderBy('sort_order')->orderBy('id');
    }

    public function hasChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }
}
