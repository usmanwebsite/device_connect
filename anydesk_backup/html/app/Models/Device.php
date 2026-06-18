<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;
        protected $fillable = [
        'mac_address',
        'ip_address',
        'device_type',
        'status',
        'last_communication',
        'firmware_version'
    ];

    public function vendorPasses()
    {
        return $this->belongsToMany(VendorPass::class, 'device_vendor_pass');
    }
}
