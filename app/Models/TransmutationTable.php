<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransmutationTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'policy_reference_id',
        'passing_initial_grade',
        'passing_transmuted_grade',
        'is_confirmed_by_admin',
        'confirmed_by',
        'confirmed_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'passing_initial_grade' => 'float',
        'passing_transmuted_grade' => 'float',
        'is_confirmed_by_admin' => 'boolean',
        'confirmed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function policyReference(): BelongsTo
    {
        return $this->belongsTo(PolicyReference::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TransmutationTableRow::class)->orderByDesc('min_initial_grade');
    }
}
