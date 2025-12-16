<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrregularSubjectFee extends Model
{
    protected $table = 'irregular_subject_fees';

    // Make sure timestamps are enabled
    public $timestamps = true;

    protected $fillable = [
        'transaction',
        'reference_number',
        'student_id',
        'irregular_subject_id',
        'subject_id',
        'units',
        'fee',
        'school_year_id',
        'status',
        'created_by',
        'approved_by',
    ];
}
