<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requirement extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'city_id');
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'locality_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RequirementResponse::class)->latest('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RequirementMatch::class);
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'active')->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function budgetLabel(): string
    {
        $fmt = fn ($v) => $this->purpose === 'sell' ? \App\Support\Money::inr($v) : '₹'.number_format($v);

        return match (true) {
            $this->budget_min && $this->budget_max => $fmt($this->budget_min).' - '.$fmt($this->budget_max),
            (bool) $this->budget_max => 'Up to '.$fmt($this->budget_max),
            (bool) $this->budget_min => 'From '.$fmt($this->budget_min),
            default => 'Flexible budget',
        };
    }
}
