<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = ['billing' => 'array', 'lines' => 'array', 'issued_at' => 'datetime'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function taxPaise(): int
    {
        return $this->cgst_paise + $this->sgst_paise + $this->igst_paise;
    }
}
