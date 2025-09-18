<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class enrollmentschedule extends Model
{
    use HasFactory;

    protected $table = 'enrollment_schedules';

    protected $fillable = [
        'school_year_id',
        'start_date',
        'end_date',
        'status',
    ];

    // Relationship: belongs to school_year
    public function schoolYear()
    {
        return $this->belongsTo(school_years::class, 'school_year_id');
    }
}
