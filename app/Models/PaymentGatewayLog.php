<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];
}
