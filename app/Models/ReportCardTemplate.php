<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'curriculum_id',
        'education_level',
        'header_subtitle',
        'disclaimer_label',
        'footer_note',
        'show_attendance',
        'show_observed_values',
        'show_lrn',
        'paper_size',
        'orientation',
        'is_default',
    ];

    protected $casts = [
        'show_attendance' => 'boolean',
        'show_observed_values' => 'boolean',
        'show_lrn' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public static function defaultTemplate(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->first()
            ?? new static([
                'name' => 'Default DepEd-Compatible SF9 Layout',
                'header_subtitle' => 'Learner Progress Report Card',
                'disclaimer_label' => 'DepEd-Compatible Learner Progress Report Card (School-Issued)',
                'show_attendance' => true,
                'show_observed_values' => true,
                'show_lrn' => true,
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'is_default' => true,
            ]);
    }
}
