<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class departments extends Model
{
    use HasFactory;
    protected $fillable = [
        'department_name',
        'abbreviation',
        'description',
        'is_archive',
    ];
}
