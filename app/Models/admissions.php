<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admissions extends Model
{
    use HasFactory;

    protected $table = 'admissions';

    protected $fillable = [
        'lrn',
        'sent_exam_schedule',
        'applicant_number',
        'academic_year_id',
        'grade_level_id',

        'school_campus_id',
        'application_type',
        'classification',
        'academic_program_id',

        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'birthdate',
        'birthplace',
        'civil_status',
        'email',
        'contact_number',
        'street_address',
        'province',
        'city',
        'barangay',
        'blood_type',

        'nationality',
        'religion',
        'ethnic_affiliation',
        'telephone_number',
        'is_4ps_member',
        'is_insurance_member',
        'is_vaccinated',
        'is_indigenous',

        'guardian_name',
        'guardian_contact',
        'mother_name',
        'mother_contact',
        'father_name',
        'father_contact',


        'last_school_attended',
        'remarks',
        'status',
        'exam_score',

        'form_137',
        'form_138',
        'birth_certificate',
        'good_moral',
        'certificate_of_completion',
    ];

    public function academic_program()
    {
        return $this->belongsTo(courses::class, 'academic_program_id');
    }

    // In your Admission model (app/Models/admissions.php)
    public function schoolCampus()
    {
        return $this->belongsTo(school_campus::class, 'school_campus_id');
    }


    public function generateTestPermitNo()
    {
        $prefix = "SNL-";
        $paddedId = str_pad($this->id, 5, '0', STR_PAD_LEFT);
        return $prefix . $paddedId;
    }

    public function exam_schedule()
    {
        return $this->hasOne(exam_schedules::class, 'applicant_id', 'id');
    }
    public function school_years()
    {
        return $this->belongsTo(school_years::class, 'academic_year_id');
    }

    public function course()
    {
        return $this->belongsTo(courses::class, 'academic_program_id');
    }
    
    public function student()
    {
        return $this->hasOne(students::class, 'admission_id');
    }

    public function gradeLevel()
{
    return $this->belongsTo(grade__levels::class, 'grade_level_id');
}
public function campus()
{
    return $this->belongsTo(school_campus::class, 'school_campus_id');
}



}
