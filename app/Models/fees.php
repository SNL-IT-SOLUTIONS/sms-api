<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class fees extends Model
{
    use HasFactory;

    protected $table = 'fees';

    protected $fillable = [
        'fee_name',
        'description',
        'default_amount',
        'is_active',
        'school_year_id',
    ];

    public function schoolYear()
    {
        return $this->belongsTo(school_years::class, 'school_year_id');
    }
}
