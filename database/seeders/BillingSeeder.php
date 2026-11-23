<?php

namespace Database\Seeders;

use App\Models\CreditPack;
use App\Models\PromotionPackage;
use Illuminate\Database\Seeder;

/** Illustrative starter prices (rupees, excluding GST). Admins manage these in the admin panel. */
class BillingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['10 contacts', 10, 99], ['25 contacts', 25, 199], ['50 contacts', 50, 349]] as $i => [$name, $credits, $price]) {
            CreditPack::firstOrCreate(['name' => $name], ['credits' => $credits, 'price' => $price, 'gst_percent' => 18, 'is_active' => true, 'sort_order' => $i]);
        }

        foreach ([['Featured, 7 days', 7, 499, false], ['Featured + homepage, 15 days', 15, 1299, true]] as $i => [$name, $days, $price, $home]) {
            PromotionPackage::firstOrCreate(['name' => $name], ['duration_days' => $days, 'price' => $price, 'gst_percent' => 18, 'show_on_homepage' => $home, 'is_active' => true, 'sort_order' => $i]);
        }
    }
}
