<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(Request $request, SubscriptionService $subscriptions)
    {
        $user = $request->user();
        $roles = Role::whereIn('slug', config('marketplace.roles'))->get();

        if ($user) {
            $roles = $roles->whereIn('id', $user->roles()->pluck('roles.id'))->values();
        }

        $packages = Package::with(['role', 'featureValues.feature'])->where('status', 'active')->where('is_free', false)->whereIn('role_id', $roles->pluck('id'))
            ->orderBy('role_id')->orderBy('display_order')->get()->groupBy('role_id');

        return view('billing.pricing', [
            'roles' => $roles,
            'packages' => $packages,
            'current' => $user ? $roles->mapWithKeys(fn ($r) => [$r->id => $subscriptions->activeFor($user, $r->slug)]) : collect(),
            'creditPacks' => CreditPack::where('is_active', true)->orderBy('sort_order')->get(),
            'usedTrials' => $user ? Subscription::where('user_id', $user->id)->pluck('package_id')->all() : [],
            'viewingAll' => ! $user,
        ]);
    }
}
