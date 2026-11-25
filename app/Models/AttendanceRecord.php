<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_enrollment_id',
        'grading_period_id',
        'month_number',
        'year_number',
        'school_days',
        'days_present',
        'days_absent',
        'days_tardy',
        'recorded_by',
    ];

    protected $casts = [
        'school_days' => 'float',
        'days_present' => 'float',
        'days_absent' => 'float',
        'days_tardy' => 'float',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
