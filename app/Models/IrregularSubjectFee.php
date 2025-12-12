<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrregularSubjectFee extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'units',
        'fee',
        'school_year_id',
        'status',
        'created_by',
        'approved_by',
    ];
}
