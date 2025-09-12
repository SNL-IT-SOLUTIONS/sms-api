<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class subjects extends Model
{
    use HasFactory;
    protected $table = 'subjects';
    protected $fillable = [
        'grade_level_id',
        'curriculum_id',
        'subject_code',
        'subject_type',
        'school_year_id',
        'subject_name',
        'units',
    ];


    public function gradeLevel()
    {
        return $this->belongsTo(grade__levels::class, 'grade_level_id');
    }
    public function students()
    {
        return $this->belongsToMany(
            Students::class,
            'student_subjects',
            'subject_id',
            'student_id'
        )
            ->withPivot(['school_year_id', 'midterm_grade', 'final_grade', 'average', 'remarks'])
            ->withTimestamps();
    }

    public function curriculum()
    {
        return $this->belongsTo(curriculums::class);
    }

    public function prerequisites()
    {
        return $this->belongsToMany(subjects::class, 'subject_prerequisites', 'subject_id', 'prerequisite_id');
    }
}
