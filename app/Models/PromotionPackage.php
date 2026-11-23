<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionPackage extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'show_on_homepage' => 'boolean', 'gst_percent' => 'float'];
}
