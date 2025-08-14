<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class exam_schedules extends Model
{
    use HasFactory;

    protected $table = 'exam_schedules';
    protected $fillable = [
        'admission_id',
        'academic_program_id',
        'test_permit_no',
        'room_id',
        'building_id',
        'campus_id',
        'exam_date',
        'exam_time_from',
        'exam_time_to',
        'academic_year',
        'exam_sent',
        'exam_score',
        'exam_status'
    ];

    // Relationships (if needed)
    public function applicant()
    {
        return $this->belongsTo(admissions::class, 'admission_id');
    }

    public function academicProgram()
    {
        return $this->belongsTo(courses::class, 'academic_program_id');
    }

    public function room()
{
    return $this->belongsTo(building_rooms::class, 'room_id');
}

public function building()
{
    return $this->belongsTo(campus_buildings::class, 'building_id');
}
public function campus()
{
    return $this->belongsTo(school_campus::class, 'campus_id');
}
}
