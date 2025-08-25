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
        'subject_name',
        'units',    
    ];


public function gradeLevel()
{
    return $this->belongsTo(grade__levels::class, 'grade_level_id');
}
public function students()
    {
        return $this->belongsToMany(students::class, 'student_subjects', 'subject_id', 'student_id');
    }

    public function curriculum()
{
    return $this->belongsTo(curriculums::class);
}


}
