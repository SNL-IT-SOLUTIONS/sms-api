<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class payments extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'amount',
        'paid_amount',
        'remaining_balance',
        'payment_method',
        'reference_no',
        'remarks',
        'status',
        'paid_at',
        'received_by',
        'receipt_no',
        'transaction',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];


    public function student()
    {
        return $this->belongsTo(students::class, 'student_id');
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
}
