<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class enrollments extends Model
{
    use HasFactory;
    protected $table = 'enrollments';
    protected $fillable = [
    'account_id',         // instead of 'student_id' if you're using the accounts table
    'admission_id',       // optional, if connected to admissions
    'course_id',
    'section_id',
    'semester',
    'year_level',
    'enrollment_status',
    'is_irregular',
    'date_enrolled',
    'tuition_fee',
    'remarks',
    'student_number',
    'is_archived',
];

}
