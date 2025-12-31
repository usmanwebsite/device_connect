<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorLocation extends Model
{
    use HasFactory;
    protected $fillable = ['location_id','meetingRoom','name','statusId'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

}
