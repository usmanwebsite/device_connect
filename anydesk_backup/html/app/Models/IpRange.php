<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpRange extends Model
{
    use HasFactory;
    protected $table = 'ip_ranges';

    protected $fillable = ['ip_range_from', 'ip_range_to'];
}
