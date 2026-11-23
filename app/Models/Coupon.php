<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
