<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Promotion;
use App\Models\PromotionPackage;
use App\Models\Property;

class PromotionService
{
    /** Starts now, or right after the listing's current promotion ends, so purchases stack without gaps. */
    public function activate(Payment $payment): Promotion
    {
        $package = PromotionPackage::findOrFail($payment->item_id);
        $property = Property::findOrFail($payment->property_id);

        $latestEnd = Promotion::where('property_id', $property->id)->where('status', 'active')->max('ends_at');
        $start = $latestEnd && now()->lt($latestEnd) ? \Illuminate\Support\Carbon::parse($latestEnd) : now();

        $promotion = Promotion::create([
            'property_id' => $property->id, 'user_id' => $payment->user_id, 'promotion_package_id' => $package->id,
            'payment_id' => $payment->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addDays($package->duration_days),
            'homepage' => $package->show_on_homepage, 'status' => 'active',
        ]);

        $this->recompute($property);

        return $promotion;
    }

    /** featured_until / homepage_until are denormalised from the active promotions for fast search ordering. */
    public function recompute(Property $property): void
    {
        $active = Promotion::where('property_id', $property->id)->where('status', 'active');

        Property::whereKey($property->id)->update([
            'featured_until' => (clone $active)->max('ends_at'),
            'homepage_until' => (clone $active)->where('homepage', true)->max('ends_at'),
        ]);
    }
}
