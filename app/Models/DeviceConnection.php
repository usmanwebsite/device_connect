<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DeviceConnection extends Model
{
    use HasFactory;
    protected $table="device_connections";
    protected $fillable =['device_id','ip','last_heartbeat'];


    public function assignments()
    {
        return $this->hasMany(DeviceLocationAssign::class, 'device_id');
    }
    
    // Helper method to check if device is online
    public function isOnline()
    {
        if (!$this->last_heartbeat) {
            return false;
        }
        
        return Carbon::parse($this->last_heartbeat)->diffInSeconds(now()) <= 60;
    }
    
    // Helper method to get current assignment
    public function currentAssignment()
    {
        return $this->assignments()->first();
    }

}
