<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class grade__levels extends Model
{
    use HasFactory;
    protected $table = 'grade_levels';
    protected $fillable = [
        'grade_level',
        'description',
        'created_at',
        'updated_at'
    ];
}
