<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class payments extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'student_id',
        'amount',
        'payment_method',
        'reference_no',
        'remarks',
    ];

    /**
     * Payment belongs to a student
     */
    public function student()
    {
        return $this->belongsTo(students::class);
    }
}
