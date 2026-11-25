<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentObservedValue extends Model
{
    use HasFactory;

    public const CORE_VALUE_STATEMENTS = [
        'MD1' => ['core_value' => 'maka_diyos', 'core_label' => '1. Maka-Diyos', 'statement' => 'Expresses one\'s spiritual beliefs while respecting the spiritual beliefs of others.'],
        'MD2' => ['core_value' => 'maka_diyos', 'core_label' => '1. Maka-Diyos', 'statement' => 'Shows adherence to ethical principles by upholding truth.'],
        'MT1' => ['core_value' => 'makatao', 'core_label' => '2. Makatao', 'statement' => 'Is sensitive to individual, social, and cultural differences.'],
        'MT2' => ['core_value' => 'makatao', 'core_label' => '2. Makatao', 'statement' => 'Demonstrates contributions toward solidarity.'],
        'MK1' => ['core_value' => 'maka_kalikasan', 'core_label' => '3. Maka-Kalikasan', 'statement' => 'Cares for the environment and utilizes resources wisely, judiciously, and economically.'],
        'MB1' => ['core_value' => 'maka_bansa', 'core_label' => '4. Maka-Bansa', 'statement' => 'Demonstrates pride in being a Filipino; exercises the rights and responsibilities of a Filipino citizen.'],
        'MB2' => ['core_value' => 'maka_bansa', 'core_label' => '4. Maka-Bansa', 'statement' => 'Demonstrates appropriate behavior in carrying out activities in the school, community, and country.'],
    ];

    public const RATING_SCALE = [
        'AO' => 'Always Observed',
        'SO' => 'Sometimes Observed',
        'RO' => 'Rarely Observed',
        'NO' => 'Not Observed',
    ];

    protected $fillable = [
        'student_enrollment_id',
        'grading_period_id',
        'core_value',
        'behavior_statement_code',
        'rating',
        'recorded_by',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
