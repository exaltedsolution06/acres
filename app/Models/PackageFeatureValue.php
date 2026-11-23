<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageFeatureValue extends Model
{
    protected $guarded = [];

    protected $casts = ['is_unlimited' => 'boolean'];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(PackageFeature::class, 'feature_id');
    }
}
