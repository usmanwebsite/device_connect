<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Path extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $fillable = ['name', 'doors'];

    // Ye method array return karegi (for forms, editing)
    public function getDoorsArrayAttribute()
    {
        if (empty($this->attributes['doors'])) {
            return [];
        }
        return explode(',', $this->attributes['doors']);
    }

    // Ye method string return karegi (for display)
    public function getDoorsStringAttribute()
    {
        return $this->attributes['doors'];
    }
    
    public function visitorTypes()
    {
        return $this->hasMany(VisitorType::class, 'path_id');
    }
}
