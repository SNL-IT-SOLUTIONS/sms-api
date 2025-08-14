<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class building_rooms extends Model
{
    use HasFactory;
    protected $table = 'building_rooms';
    protected $fillable = [
        'room_name',
        'building_id',
        'description',
        'room_size',
        'is_archived',
    ];

    public function building()
    {
        return $this->belongsTo(campus_buildings::class, 'building_id');
    }
}
