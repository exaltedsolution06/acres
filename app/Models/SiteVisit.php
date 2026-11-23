<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisit extends Model
{
    protected $guarded = [];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
