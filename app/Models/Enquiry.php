<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'interest_at' => 'datetime', 'enquired_at' => 'datetime', 'closed_at' => 'datetime',
        'follow_up_at' => 'datetime', 'last_activity_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EnquiryNote::class)->latest('id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(SiteVisit::class)->latest('scheduled_at');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, config('engagement.closed_statuses'), true);
    }

    /** What the seeker sees: they do not get the poster's internal pipeline. */
    public function seekerStatus(): string
    {
        return match (true) {
            $this->isClosed() => 'Closed',
            $this->status === 'new' => 'Sent',
            default => 'In progress',
        };
    }
}
