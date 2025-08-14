<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

class accounts extends Authenticatable
{
      use HasApiTokens, Notifiable;

      protected $appends = ['profile_picture_url'];

    public function getProfilePictureUrlAttribute()
    {
        return $this->profile_picture ? asset($this->profile_picture) : null;
    }


      protected $table = 'accounts';
     protected $fillable = [
    'email',
    'username',
    'password',
    'profile_picture',
    'surname',
    'given_name',
    'middle_name',
    'middle_initial',
    'suffix',
    'verification_code',
    'date_of_birth',
    'place_of_birth',
    'gender',
    'civil_status',
    'street_address',
    'province',
    'city',
    'barangay',
    'nationality',
    'religion',
    'ethnic_affiliation',
    'telephone_number',
    'mobile_number',
    'is_4ps_member',
    'is_insurance_member',
    'is_vaccinated',
    'is_indigenous',
    'status',
    'is_verified',
    'is_archived',
    'user_type_id',
    'department_id'
];



public function userType()
{
    return $this->belongsTo(user_types::class, 'user_type_id');
}

public function student()
{
    return $this->hasOne(students::class, 'admission_id', 'id');
}


}
