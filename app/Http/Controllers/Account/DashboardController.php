<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\PackageFeature;
use App\Services\ContactCreditService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Features shown on each role's dashboard. */
    private const FEATURES = [
        'owner' => ['active_listings', 'photo_limit', 'video_upload', 'featured_listing', 'lead_management', 'daily_contact_views'],
        'tenant' => ['daily_property_views', 'daily_interest', 'daily_contact_views', 'saved_properties', 'requirement_posting', 'map_search'],
        'buyer' => ['daily_property_views', 'daily_interest', 'daily_contact_views', 'saved_properties', 'requirement_posting', 'map_search'],
        'broker' => ['active_listings', 'daily_property_views', 'daily_contact_views', 'broker_crm', 'lead_management', 'featured_listing', 'requirement_posting', 'search_priority'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $slug = $user->activeRoleSlug() ?? $user->roles()->value('slug');

        abort_unless($slug, 403, 'No role is assigned to this account.');

        return redirect()->route('dashboard.role', $slug);
    }

    public function show(
        Request $request,
        string $role,
        SubscriptionService $subs,
        PermissionService $permissions,
        UsageTrackingService $usage,
        ContactCreditService $credits,
        RoleService $roles,
    ) {
        $user = $request->user();

        abort_unless($user->hasRole($role), 403);

        // The URL is the source of truth for which role the person is looking at.
        if ($user->activeRoleSlug() !== $role) {
            $roles->switchTo($user, $role);
        }

        $keys = self::FEATURES[$role] ?? [];
        $features = PackageFeature::whereIn('key', $keys)->where('is_active', true)->get()->keyBy('key');

        $rows = collect($keys)->filter(fn ($k) => $features->has($k))->map(function ($key) use ($features, $user, $role, $permissions, $usage) {
            $feature = $features[$key];

            if ($feature->type === 'boolean') {
                return ['name' => $feature->name, 'display' => $permissions->can($user, $key, $role) ? 'Included' : 'Not included', 'daily' => false];
            }

            $limit = $permissions->limit($user, $key, $role);

            return [
                'name' => $feature->name,
                'display' => $limit === null ? 'Unlimited' : (string) $limit,
                'daily' => (bool) $feature->is_daily,
                'used' => $feature->is_daily ? $usage->usedToday($user, $key) : null,
                'remaining' => $feature->is_daily ? $usage->remaining($user, $key, $role) : null,
            ];
        });

        return view('dashboard.role', [
            'user' => $user->load('roles', 'profile'),
            'role' => $role,
            'package' => $subs->packageFor($user, $role),
            'subscription' => $subs->activeFor($user, $role),
            'rows' => $rows,
            'credits' => $credits->balance($user),
        ]);
    }

    public function switchRole(Request $request, RoleService $roles)
    {
        $data = $request->validate(['role' => ['required', 'in:'.implode(',', config('marketplace.roles'))]]);

        $roles->switchTo($request->user(), $data['role']); // throws AuthorizationException (403) if not owned

        return redirect()->route('dashboard.role', $data['role']);
    }
}
