<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRANSFERRED_OUT = 'transferred_out';
    public const STATUS_GRADUATED = 'graduated';
    public const STATUS_DROPPED = 'dropped';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'student_number',
        'lrn',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'date_of_birth',
        'photo_path',
        'address',
        'guardian_name',
        'guardian_contact',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->orderByDesc('school_year_id')->orderByDesc('id');
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->where('is_current', true)->latestOfMany('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name ? mb_substr($this->middle_name, 0, 1) . '.' : null,
            $this->last_name,
            $this->suffix,
        ]);

        return implode(' ', $parts);
    }

    public function getFormalNameAttribute(): string
    {
        $base = "{$this->last_name}, {$this->first_name}";
        if ($this->middle_name) {
            $base .= ' ' . mb_substr($this->middle_name, 0, 1) . '.';
        }
        if ($this->suffix) {
            $base .= ' ' . $this->suffix;
        }

        return $base;
    }
}
