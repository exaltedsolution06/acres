<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
