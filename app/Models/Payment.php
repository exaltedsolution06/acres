<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = ['billing' => 'array', 'meta' => 'array', 'paid_at' => 'datetime', 'fulfilled_at' => 'datetime', 'gst_percent' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('type', 'invoice');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('type', 'credit_note');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class)->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** URLs use the transaction id, not the sequential row id. */
    public function getRouteKeyName(): string
    {
        return 'txn_id';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function total(): string
    {
        return Money::paise($this->total_paise);
    }
}
