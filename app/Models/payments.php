<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class payments extends Model
{
    use HasFactory;
     protected $table = 'payments';

    // Fillable fields for mass assignment
    protected $fillable = [
        'student_id',
        'amount',
        'paid_amount',
        'payment_method',
        'reference_no',
        'remarks',
        'status',
        'paid_at',
        'received_by',
        'receipt_no'
    ];

    // Casts for convenience
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];


    /**
     * Payment belongs to a student
     */
   public function student()
{
    return $this->belongsTo(students::class, 'student_id'); // again, must match the column
}
}


