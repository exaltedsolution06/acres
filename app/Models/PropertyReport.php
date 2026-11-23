<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyReport extends Model
{
    protected $guarded = [];

    protected $casts = ['handled_at' => 'datetime'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reasonLabel(): string
    {
        return config("trust.report_reasons.{$this->reason}", $this->reason);
    }
}
