<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyCategory extends Model
{
    protected $guarded = [];

    protected $casts = ['has_bhk' => 'boolean', 'is_active' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
