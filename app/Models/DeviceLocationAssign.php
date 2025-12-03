<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLocationAssign extends Model
{
    use HasFactory;
    protected $table="device_location_assigns";
    protected $fillable=['is_type','device_id','location_id'];

    public function device()
    {
        return $this->belongsTo(DeviceConnection::class);
    }

    public function location()
    {
        return $this->belongsTo(VendorLocation::class, 'location_id');
    }
}
