<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class student_subjects extends Model
{
    use HasFactory;

    protected $table = 'student_subjects';

    protected $fillable = [
        'student_id',
        'subject_id',
        'school_year_id',
        'final_rating',
        'remarks',
    ];
}
    