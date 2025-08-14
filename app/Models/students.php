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
        'admission_id',
        'student_number',
        'password',
        'profile_img',
        'student_status',
        'section_id',
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


}

