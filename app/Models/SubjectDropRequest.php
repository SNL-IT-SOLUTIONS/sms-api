<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubjectDropRequest extends Model
{
    use HasFactory;

    protected $table = 'subject_drop_requests';

    protected $fillable = [
        'student_subject_id',
        'student_id',
        'status',
        'remarks',
        'requested_by'
    ];

    // Relationships
    public function studentSubject()
    {
        return $this->belongsTo(student_subjects::class, 'student_subject_id');
    }

    public function student()
    {
        return $this->belongsTo(students::class);
    }

    public function requester()
    {
        return $this->belongsTo(students::class, 'requested_by');
    }
}
