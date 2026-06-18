<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPass extends Model
{
    use HasFactory;
        protected $fillable = [
        'qr_string',
        'card_id',
        'name',
        'max_usage',
        'used_count',
        'valid_from',
        'valid_until',
        'status',
        'last_used'
    ];

    public function devices()
    {
        return $this->belongsToMany(Device::class, 'device_vendor_pass');
    }

    public function qrLogs()
    {
        return $this->hasMany(QRLog::class);
    }
}
