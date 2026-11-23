<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function find(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper(trim($code)))->first();
    }

    public function assertUsable(Coupon $coupon, User $user, string $purpose, int $amountPaise): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['coupon' => $message]);

        if (! $coupon->is_active) {
            $fail('This coupon is not active.');
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            $fail('This coupon is not valid yet.');
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            $fail('This coupon has expired.');
        }

        if ($coupon->applies_to !== 'all' && $coupon->applies_to !== $purpose) {
            $fail('This coupon does not apply to this purchase.');
        }

        if ($coupon->min_amount && $amountPaise < $coupon->min_amount * 100) {
            $fail('This coupon needs a minimum purchase of ₹'.number_format($coupon->min_amount).'.');
        }

        if ($coupon->usage_limit !== null && $coupon->redemptions()->count() >= $coupon->usage_limit) {
            $fail('This coupon has been fully used.');
        }

        if (CouponRedemption::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
            $fail('You have already used this coupon.');
        }
    }

    /** Discount in paise, never more than the amount itself. */
    public function discount(Coupon $coupon, int $amountPaise): int
    {
        $discount = $coupon->type === 'percent'
            ? (int) floor($amountPaise * $coupon->value / 100)
            : $coupon->value * 100;

        if ($coupon->type === 'percent' && $coupon->max_discount) {
            $discount = min($discount, $coupon->max_discount * 100);
        }

        return min($discount, $amountPaise);
    }
}
