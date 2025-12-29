<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLocationAssign extends Model
{
    use HasFactory;
    
    // Possible values for is_type
    const CHECK_IN = 'check_in';
    const CHECK_OUT = 'check_out';
    
    protected $table = "device_location_assigns";
    protected $fillable = ['is_type', 'device_id', 'location_id'];

    public function device()
    {
        return $this->belongsTo(DeviceConnection::class);
    }

    public function location()
    {
        return $this->belongsTo(VendorLocation::class, 'location_id');
    }
    
    // Helper methods
    public function isCheckIn()
    {
        return $this->is_type === self::CHECK_IN;
    }
    
    public function isCheckOut()
    {
        return $this->is_type === self::CHECK_OUT;
    }
    
    // Get type as display text
    public function getTypeTextAttribute()
    {
        return match($this->is_type) {
            self::CHECK_IN => 'Check-In',
            self::CHECK_OUT => 'Check-Out',
            default => ucfirst(str_replace('_', ' ', $this->is_type))
        };
    }
    
    // Get all possible types
    public static function getTypes()
    {
        return [
            self::CHECK_IN => 'Check-In',
            self::CHECK_OUT => 'Check-Out'
        ];
    }
}
