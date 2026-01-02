<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityAlertPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_alert',
        'priority'
    ];

    protected $casts = [
        'priority' => 'string'
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->priority)) {
                $model->priority = 'low';
            }
        });
    }

}
