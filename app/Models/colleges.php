<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class colleges extends Model
{
    use HasFactory;
    protected $table = 'colleges';
    protected $fillable = [
        'college_name',
        'abbreviation',
        'description',
        'course_id',
    ];

    public function courses()
    {
        return $this->belongsToMany(courses::class, 'college_course');
    }
}
