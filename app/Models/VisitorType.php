<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitorType extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_type',
        'path_id'
    ];

    public function path()
    {
        return $this->belongsTo(Path::class);
    }

}
