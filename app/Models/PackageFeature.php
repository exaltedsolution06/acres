<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageFeature extends Model
{
    protected $guarded = [];

    protected $casts = ['is_daily' => 'boolean', 'is_active' => 'boolean', 'options' => 'array'];
}
