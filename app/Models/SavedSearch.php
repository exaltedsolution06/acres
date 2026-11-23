<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    protected $guarded = [];

    protected $casts = ['filters' => 'array', 'last_checked_at' => 'datetime'];
}
