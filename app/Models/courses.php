<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class courses extends Model
{
    use HasFactory;
    protected $table = 'courses';
    protected $fillable = [
        'course_name',
        'course_code',
        'curriculum_id',
        'course_description',
        'course_units',
        'is_archive',
    ];

public function subjects()
{
    return $this->hasMany(subjects::class, 'course_id'); // NOT courses_id
}

public function curriculums()
{
    return $this->hasMany(curriculums::class);
}

}
