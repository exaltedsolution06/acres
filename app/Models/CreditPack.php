<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPack extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'gst_percent' => 'float'];
}
