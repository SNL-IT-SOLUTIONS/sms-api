<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class subjects extends Model
{
    use HasFactory;
    protected $table = 'subjects';
       protected $fillable = [
        'course_id',
        'curriculum_id',
        'subject_code',
        'subject_name',
        'units',
    ];


public function course()
{
    return $this->belongsTo(courses::class, 'course_id');
}
public function students()
    {
        return $this->belongsToMany(students::class, 'student_subject', 'subject_id', 'student_id');
    }

    public function curriculum()
{
    return $this->belongsTo(curriculums::class);
}


}
