<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionSubjectSchedule extends Model
{
    use HasFactory;

    protected $table = 'section_subject_schedule';

    protected $fillable = [
        'section_id',
        'subject_id',
        'day',
        'start_time',
        'end_time',
        'room_id',
        'teacher_id',
    ];

    // Relationships
    public function section()
    {
        return $this->belongsTo(sections::class);
    }

    public function subject()
    {
        return $this->belongsTo(subjects::class);
    }

    public function teacher()
    {
        return $this->belongsTo(accounts::class, 'teacher_id');
    }
    public function room()
    {
        return $this->belongsTo(building_rooms::class, 'room_id', 'id');
    }
}
