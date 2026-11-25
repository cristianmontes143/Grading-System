<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'title',
        'publication_date',
        'applicable_grade_levels',
        'implementation_school_year',
        'source_url',
        'is_verified',
        'is_confirmed_by_admin',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'publication_date' => 'date',
        'is_verified' => 'boolean',
        'is_confirmed_by_admin' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }
}
