<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceAccessLog extends Model
{
    use HasFactory;
    protected $table = 'device_access_logs';
    
    protected $fillable = [
        'staff_no',
        'access_granted',
        'location_name',
        'acknowledge',
        'created_at'
    ];

    protected $casts = [
        'access_granted' => 'boolean',
        'acknowledge' => 'boolean',
        'created_at' => 'datetime'
    ];
}
