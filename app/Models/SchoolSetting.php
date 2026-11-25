<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_name',
        'school_id_code',
        'region',
        'division',
        'district',
        'address',
        'contact_number',
        'email',
        'principal_name',
        'principal_title',
        'logo_path',
        'attendance_enabled',
        'observed_values_enabled',
        'parent_portal_enabled',
        'default_calendar_mode',
    ];

    protected $casts = [
        'attendance_enabled' => 'boolean',
        'observed_values_enabled' => 'boolean',
        'parent_portal_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'school_name' => config('app.name', 'School Grading Management System'),
            'principal_title' => 'School Principal',
            'attendance_enabled' => true,
            'observed_values_enabled' => true,
            'parent_portal_enabled' => true,
            'default_calendar_mode' => 'trimester',
        ]);
    }
}
