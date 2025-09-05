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
    ];
}
