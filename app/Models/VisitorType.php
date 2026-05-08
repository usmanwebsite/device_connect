<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorType extends Model
{
    use SoftDeletes;
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
