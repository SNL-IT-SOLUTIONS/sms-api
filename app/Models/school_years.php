<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class school_years extends Model
{
    use HasFactory;
    protected $table = 'school_years';
    protected $fillable = [
        'school_year',
        'semester',
        'enrollment_start_date',
        'enrollment_end_date',
        'is_archive',
        'is_recent'
    ];
}
