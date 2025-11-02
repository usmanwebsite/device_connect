<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QRLog extends Model
{
    use HasFactory;
        protected $fillable = [
        'vendor_pass_id',
        'device_id',
        'qr_data',
        'reader_id',
        'access_granted',
        'reason'
    ];

    public function vendorPass()
    {
        return $this->belongsTo(VendorPass::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
