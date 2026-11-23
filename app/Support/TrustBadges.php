<?php

namespace App\Support;

use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;

/** Badges always state what was checked. None implies legal title verification. */
class TrustBadges
{
    /** @return array<int, array{0: string, 1: string}> [label, what it means] */
    public static function forPoster(User $user, string $postedBy): array
    {
        $badges = [];

        if ($user->hasVerifiedMobile()) {
            $badges[] = ['Mobile verified', 'The phone number was confirmed with a one-time code.'];
        }

        if ($user->email && $user->hasVerifiedEmail()) {
            $badges[] = ['Email verified', 'The email address was confirmed.'];
        }

        $until = $postedBy === 'broker' ? $user->broker_verified_until : $user->owner_verified_until;

        if ($until?->isFuture()) {
            $type = config('trust.types.'.($postedBy === 'broker' ? 'broker' : 'owner'));
            $badges[] = [$type['label'], $type['explain']];
        }

        $paid = Subscription::where('user_id', $user->id)->where('status', 'active')->whereIn('source', ['purchase', 'renewal'])
            ->where('price_paid', '>', 0)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists();

        if ($paid) {
            $badges[] = ['Premium advertiser', 'Holds a paid plan. This does not mean the listing itself was checked.'];
        }

        return $badges;
    }

    /** @return array<int, array{0: string, 1: string}> */
    public static function forProperty(Property $property): array
    {
        if ($property->documents_reviewed_until?->isFuture()) {
            $type = config('trust.types.property_documents');

            return [[$type['label'], $type['explain']]];
        }

        return [];
    }
}
