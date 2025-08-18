<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'students'; // ✅ matches your table

    protected $fillable = [
        'exam_schedules_id',
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
        'academic_year_id',
        'year_level_id',
    ];

    // 🔗 Relationships
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

    public function academicYear()
    {
        return $this->belongsTo(school_years::class, 'academic_year_id');
    }

    public function yearLevel()
    {
        return $this->belongsTo(year_levels::class, 'year_level_id');
    }
}
