<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    protected $guarded = [];

    protected $casts = ['meta' => 'array', 'usage_date' => 'date:Y-m-d'];
}
