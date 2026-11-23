<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /** Current paid/trial subscription for a role, including the grace period. */
    public function activeFor(User $user, string $roleSlug): ?Subscription
    {
        $now = now();

        return Subscription::query()
            ->where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('slug', $roleSlug))
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('ends_at')
                ->orWhere('ends_at', '>=', $now)
                ->orWhere('grace_ends_at', '>=', $now))
            ->with('package')
            ->latest('starts_at')
            ->first();
    }

    /** Package that governs the user's limits for a role: active subscription, else the role's default free package. */
    public function packageFor(User $user, string $roleSlug): ?Package
    {
        return $this->activeFor($user, $roleSlug)?->package
            ?? Package::query()
                ->whereHas('role', fn ($q) => $q->where('slug', $roleSlug))
                ->where('is_default', true)
                ->where('status', 'active')
                ->first();
    }

    /**
     * Activate a package. Call this ONLY after server-side payment verification
     * (or for admin grants); never from a browser success callback.
     */
    public function subscribe(
        User $user,
        Package $package,
        ?int $paymentId = null,
        string $source = 'purchase',
        ?float $pricePaid = null,
    ): Subscription {
        return DB::transaction(function () use ($user, $package, $paymentId, $source, $pricePaid) {
            Subscription::where('user_id', $user->id)
                ->where('role_id', $package->role_id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']); // replaced by the new plan

            $days = $source === 'trial' ? $package->trial_days : $package->validity_days;
            $starts = now();
            $ends = $days ? $starts->copy()->addDays($days) : null;
            $grace = $ends ? $ends->copy()->addDays($package->grace_days) : null;

            return Subscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'role_id' => $package->role_id,
                'status' => 'active',
                'starts_at' => $starts,
                'ends_at' => $ends,
                'grace_ends_at' => $grace,
                'price_paid' => $pricePaid ?? (float) $package->effectivePrice(),
                'auto_renew' => $package->auto_renew,
                'payment_id' => $paymentId,
                'source' => $source,
            ]);
        });
    }

    /** Renewal of the same plan: extend the current subscription instead of starting a new one, so no paid days are lost. */
    public function renew(Subscription $subscription, Package $package, ?int $paymentId = null, ?float $pricePaid = null): Subscription
    {
        $base = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at : now();
        $ends = $base->copy()->addDays((int) $package->validity_days);

        $subscription->forceFill([
            'ends_at' => $ends,
            'grace_ends_at' => $ends->copy()->addDays($package->grace_days),
            'status' => 'active',
            'price_paid' => (float) $subscription->price_paid + ($pricePaid ?? (float) $package->effectivePrice()),
            'payment_id' => $paymentId ?? $subscription->payment_id,
            'reminded_at' => null,
            'source' => 'renewal',
        ])->save();

        return $subscription;
    }

    /** Mark subscriptions whose end (and grace) date has passed as expired. */
    public function expireDue(): int
    {
        $now = now();

        return Subscription::query()
            ->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->where(fn ($a) => $a->whereNotNull('grace_ends_at')->where('grace_ends_at', '<', $now))
                  ->orWhere(fn ($b) => $b->whereNull('grace_ends_at')->whereNotNull('ends_at')->where('ends_at', '<', $now));
            })
            ->update(['status' => 'expired']);
    }
}
