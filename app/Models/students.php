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
    'curriculum_id',
    'exam_schedules_id',
    'student_number',
    'password',
    'profile_img',
    'student_status',
    'course_id',
    'misc_fee',
    'section_id',
    'academic_year_id',
    'grade_level_id',
    'units_fee',
    'tuition_fee',
    'is_active',
    'has_form137',
    'has_form138',
    'has_good_moral',
    'has_certificate_of_completion',
    'has_birth_certificate',
    'enrollment_status',
    'is_enrolled',
    'is_active',
];
      protected $hidden = [
        'password',
    ];

    public function subjects()
    {
        return $this->belongsToMany(
            subjects::class,   // related model
            'student_subjects', // pivot table
            'student_id',       // FK on pivot for student
            'subject_id'        // FK on pivot for subject
        )
        ->withPivot(['school_year_id',  'final_rating', 'remarks'])
        ->withTimestamps();
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

    public function section()
    {
        return $this->belongsTo(sections::class, 'section_id'); 
    }
public function payments()
{
    return $this->hasMany(payments::class, 'student_id'); // must match the column in payments table
}

public function campus()
{
    return $this->belongsTo(school_campus::class, 'school_campus_id');
}

// Students.php
public function curriculum()
{
    return $this->belongsTo(curriculums::class, 'curriculum_id');
}



public function schedules()
{
    return $this->hasManyThrough(
        SectionSubjectSchedule::class, 
        sections::class,                
        'id',                          
        'section_id',                  
        'section_id',                 
        'id'                         
    )->with(['subject', 'teacher', 'room']);
}






}

