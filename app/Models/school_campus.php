<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class school_campus extends Model
{
    use HasFactory;
    protected $table = 'school_campus';
    protected $fillable = [
        'campus_name',
        'campus_description',
    ];
}
