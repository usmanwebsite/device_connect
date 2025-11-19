<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLocationAssign extends Model
{
    use HasFactory;
    protected $table="device_location_assigns";
    protected $fillable=['device_id','location_id'];
}
