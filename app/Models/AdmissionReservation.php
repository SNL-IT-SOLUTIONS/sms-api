<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionReservation extends Model
{
    protected $fillable = [
        'admission_id',
        'academic_year_id',
        'reservation_date',
    ];

    // Relationships (optional, but helpful)
    public function admission()
    {
        return $this->belongsTo(admissions::class, 'admission_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(school_years::class, 'academic_year_id');
    }

    public function account()
{
    return $this->belongsTo(accounts::class, 'account_id', 'id');
}


}
