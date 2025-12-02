<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolInfo extends Model
{
    use HasFactory;

    protected $table = 'school_information';

    // Allow mass assignment (
    protected $fillable = [
        'school_name',
        'school_logo',
        'slogan',
        'address',
        'city',
        'province',
        'postal_code',
        'contact_number',
        'telephone_number',
        'email',
        'website',
    ];
}
