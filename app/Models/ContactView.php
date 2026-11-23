<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactView extends Model
{
    protected $guarded = [];

    protected $casts = ['charged' => 'boolean'];
}
