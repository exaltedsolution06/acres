<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const OWNER = 'owner';
    public const TENANT = 'tenant';
    public const BUYER = 'buyer';
    public const BROKER = 'broker';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
