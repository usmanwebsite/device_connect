<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'statusId',
        'meetingRoom'
    ];

    // Relationship with vendor locations (sub-locations)
    public function vendorLocations()
    {
        return $this->hasMany(VendorLocation::class);
    }
}

