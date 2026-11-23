<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $tz = config('marketplace.timezone');
        $monthStart = now($tz)->startOfMonth()->utc();

        $roleCounts = Role::query()
            ->leftJoin('user_roles', 'roles.id', '=', 'user_roles.role_id')
            ->groupBy('roles.id', 'roles.name')
            ->orderBy('roles.id')
            ->get(['roles.name', DB::raw('count(user_roles.id) as total')]);

        // Revenue = paid payments (incl. GST); refunded payments are excluded.
        $paid = Payment::where('status', 'paid');

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'roleCounts' => $roleCounts,
            'activeSubs' => Subscription::where('status', 'active')->count(),
            'expiredSubs' => Subscription::where('status', 'expired')->count(),
            'revenueTotal' => (clone $paid)->sum('total_paise') / 100,
            'revenueMonth' => (clone $paid)->where('paid_at', '>=', $monthStart)->sum('total_paise') / 100,
            'registrationsToday' => User::where('created_at', '>=', now($tz)->startOfDay()->utc())->count(),
            'registrations7d' => User::where('created_at', '>=', now($tz)->subDays(6)->startOfDay()->utc())->count(),
            'topPackages' => Subscription::query()
                ->join('packages', 'packages.id', '=', 'subscriptions.package_id')
                ->where('subscriptions.status', 'active')
                ->groupBy('packages.id', 'packages.name')
                ->orderByDesc('total')->limit(5)
                ->get(['packages.name', DB::raw('count(*) as total')]),
        ]);
    }
}
