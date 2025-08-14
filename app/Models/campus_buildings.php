<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class campus_buildings extends Model
{
    use HasFactory;
    protected $table = 'campus_buildings';
    protected $fillable = [
        'building_name',
        'description',
        'campus_id',
    ];


    public function campus()
    {
        return $this->belongsTo(school_campus::class, 'campus_id');
    }
}
