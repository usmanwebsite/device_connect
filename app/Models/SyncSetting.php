<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncSetting extends Model
{
    use HasFactory;
    protected $table = 'sync_settings';

    protected $fillable = [
        'ip_host',
        'db_name',
        'db_user',
        'db_password',
    ];
}
