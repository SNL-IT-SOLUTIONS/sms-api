<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class students extends Model
{
    use HasApiTokens;
    use HasFactory;
     protected $table = 'students';

    protected $fillable = [
         'exam_schedules_id',
        'student_number',
        'password',
        'profile_img',
        'student_status',
        'course_id',
        'section_id',
        'academic_year_id',
        'grade_level_id',
        'units_fee',
        'misc_fee',
        'tuition_fee',
        'is_active',
    ];
      protected $hidden = [
        'password',
    ];

    public function subjects()
    {
        return $this->belongsToMany(subjects::class, 'student_subject', 'student_id', 'subject_id');
    }
public function admission()
{
    return $this->belongsTo(admissions::class, 'admission_id');
}

public function examSchedule()
{
    return $this->belongsTo(exam_schedules::class, 'exam_schedules_id');
}

public function course()
{
    return $this->belongsTo(courses::class, 'course_id');
}



}

