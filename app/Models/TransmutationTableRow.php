<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransmutationTableRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transmutation_table_id',
        'min_initial_grade',
        'max_initial_grade',
        'transmuted_grade',
    ];

    protected $casts = [
        'min_initial_grade' => 'float',
        'max_initial_grade' => 'float',
        'transmuted_grade' => 'float',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(TransmutationTable::class, 'transmutation_table_id');
    }
}
