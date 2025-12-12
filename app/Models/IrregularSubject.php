<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class IrregularSubject extends Model
{
    protected $table = 'irregular_subjects';

    protected $fillable = [
        'student_id',
        'subject_id',
        'status',
        'school_year_id',
        'final_rating',
        'remarks',
    ];

    public function subject()
    {
        return $this->belongsTo(subjects::class, 'subject_id');
    }
}
