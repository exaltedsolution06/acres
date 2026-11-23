<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CreditPack;
use App\Models\Package;
use App\Models\Property;
use App\Models\PromotionPackage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Prices are computed on the server from the database, never from anything the browser sends.
 * Listed prices exclude GST; GST is added on top of the discounted amount. All amounts in paise.
 */
class PriceCalculator
{
    public function __construct(private CouponService $coupons, private SubscriptionService $subscriptions)
    {
    }

    /** @return array{subtotal: int, discount: int, tax: int, total: int, gst: float, description: string, coupon: ?Coupon, meta: array} */
    public function quote(User $user, string $purpose, int $itemId, ?string $couponCode = null, ?Property $property = null): array
    {
        [$base, $gst, $description, $meta, $autoDiscount] = match ($purpose) {
            'subscription' => $this->subscription($user, $itemId),
            'credit_pack' => $this->creditPack($itemId),
            'promotion' => $this->promotion($user, $itemId, $property),
            default => throw ValidationException::withMessages(['purpose' => 'Unknown purchase type.']),
        };

        $discount = $autoDiscount;
        $coupon = null;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $coupon = $this->coupons->find($couponCode) ?? throw ValidationException::withMessages(['coupon' => 'This coupon code is not valid.']);
            $this->coupons->assertUsable($coupon, $user, $purpose, $base - $autoDiscount);
            $discount += $this->coupons->discount($coupon, $base - $autoDiscount);
        }

        $taxable = max(0, $base - $discount);
        $tax = (int) round($taxable * $gst / 100);

        return [
            'subtotal' => $base, 'discount' => $discount, 'tax' => $tax, 'total' => $taxable + $tax,
            'gst' => $gst, 'description' => $description, 'coupon' => $coupon, 'meta' => $meta,
        ];
    }

    private function subscription(User $user, int $id): array
    {
        $package = Package::with('role')->where('status', 'active')->find($id);

        if (! $package || $package->is_free || (float) $package->price <= 0) {
            throw ValidationException::withMessages(['item' => 'This plan is not available for purchase.']);
        }

        if (! $user->roles()->where('roles.id', $package->role_id)->exists()) {
            throw ValidationException::withMessages(['item' => "Add the {$package->role->name} role to your account to buy this plan."]);
        }

        $base = (int) round((float) $package->effectivePrice() * 100);
        $renewal = $this->subscriptions->activeFor($user, $package->role->slug)?->package_id === $package->id;
        $renewalDiscount = $renewal ? (int) floor($base * (float) $package->renewal_discount_percent / 100) : 0;

        return [
            $base, (float) $package->gst_percent,
            "{$package->name} plan ({$package->role->name}), {$package->validity_days} days",
            ['package_id' => $package->id, 'renewal' => $renewal], $renewalDiscount,
        ];
    }

    private function creditPack(int $id): array
    {
        $pack = CreditPack::where('is_active', true)->find($id) ?? throw ValidationException::withMessages(['item' => 'This pack is not available.']);

        return [$pack->price * 100, $pack->gst_percent, "{$pack->name}: {$pack->credits} contact credits", ['credits' => $pack->credits], 0];
    }

    private function promotion(User $user, int $id, ?Property $property): array
    {
        $package = PromotionPackage::where('is_active', true)->find($id) ?? throw ValidationException::withMessages(['item' => 'This promotion is not available.']);

        if (! $property || $property->user_id !== $user->id || ! $property->isVisible()) {
            throw ValidationException::withMessages(['property' => 'Choose one of your live listings to promote.']);
        }

        return [
            $package->price * 100, $package->gst_percent,
            "{$package->name}: {$package->duration_days} days for \"{$property->title}\"",
            ['property_public_id' => $property->public_id], 0,
        ];
    }
}
