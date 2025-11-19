<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceConnection extends Model
{
    use HasFactory;
    protected $table="device_connections";
    protected $fillable =['device_id','ip','last_heartbeat'];
}
