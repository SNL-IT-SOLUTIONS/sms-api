<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class enrollments extends Model
{
    use HasFactory;

    protected $table = 'enrollments';

    protected $fillable = [
        'transaction',
        'original_tuition_fee',
        'reference_number',
        'admission_id',
        'total_tuition_fee',
        'student_id',
        'total_amount',
        'school_year_id',
        'curriculum_id',
        'exam_schedule_id', // 🔧 fixed naming
        'course_code',
        'student_number',
        'password',
        'profile_img',
        'student_status',
        'section_id',
        'course_id',
        'is_active',
        'tuition_fee',
        'units_fee',
        'misc_fee',
        'enrollment_status',
        'payment_status',
        'has_form137',
        'has_form138',
        'has_good_moral',
        'has_certificate_of_completion',
        'has_birth_certificate',
        'academic_year_id',
        'grade_level_id',
        'created_by',
        'updated_by',
    ];

    // 🔗 Relationships
    public function examSchedule()
    {
        return $this->belongsTo(exam_schedules::class, 'exam_schedule_id');
    }

    public function course()
    {
        return $this->belongsTo(courses::class, 'course_id');
    }

    public function section()
    {
        return $this->belongsTo(sections::class, 'section_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(school_years::class, 'academic_year_id');
    }

    public function gradeLevel()
    {
        return $this->belongsTo(grade__levels::class, 'grade_level_id');
    }
    public function student()
    {
        return $this->belongsTo(students::class, 'student_id');
    }
    public function curriculum()
    {
        return $this->belongsTo(curriculums::class, 'curriculum_id');
    }
    public function admission()
    {
        return $this->belongsTo(admissions::class, 'admission_id');
    }
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
}
