<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShsStrand extends Model
{
    use HasFactory;

    protected $fillable = [
        'shs_track_id',
        'code',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(ShsTrack::class, 'shs_track_id');
    }
}
