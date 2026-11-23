<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_free' => 'boolean',
        'is_default' => 'boolean',
        'is_recommended' => 'boolean',
        'auto_renew' => 'boolean',
        'refund_eligible' => 'boolean',
        'rules' => 'array',
        'price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function featureValues(): HasMany
    {
        return $this->hasMany(PackageFeatureValue::class);
    }

    public function effectivePrice(): string
    {
        return (string) ($this->discounted_price ?? $this->price);
    }
}
