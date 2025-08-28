<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class curriculums extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_name',
        'curriculum_description',
        'course_id',
    ];

   public function subjects()
{
    return $this->belongsToMany(subjects::class, 'curriculum_subject', 'curriculum_id', 'subject_id');
}

public function course()
{
    return $this->belongsTo(courses::class);
}

public function students()
{
    return $this->hasMany(students::class, 'curriculum_id');
}
}
