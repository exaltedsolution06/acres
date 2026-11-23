<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'homepage' => 'boolean'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PromotionPackage::class, 'promotion_package_id');
    }
}
