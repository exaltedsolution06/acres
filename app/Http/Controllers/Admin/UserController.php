<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactCredit;
use App\Models\DailyUsageSummary;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ContactCreditService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('mobile', 'like', "%{$term}%")))
            ->when($request->role, fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('slug', $role)))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::all()]);
    }

    public function show(User $user, SubscriptionService $subs, PermissionService $permissions)
    {
        $user->load('roles', 'activeRole');
        $today = app(UsageTrackingService::class)->today();
        $summaries = DailyUsageSummary::where('user_id', $user->id)->where('usage_date', $today)->get()->keyBy('feature_key');
        $dailyFeatures = PackageFeature::where('is_daily', true)->where('is_active', true)->get();

        $plans = $user->roles->map(fn ($role) => [
            'role' => $role,
            'subscription' => $subs->activeFor($user, $role->slug),
            'package' => $subs->packageFor($user, $role->slug),
        ]);

        $usage = $user->active_role_id ? $dailyFeatures->map(fn ($f) => [
            'feature' => $f,
            'limit' => $permissions->limit($user, $f->key),
            'used' => (int) ($summaries[$f->key]->used ?? 0),
            'bonus' => (int) ($summaries[$f->key]->bonus ?? 0),
        ]) : collect();

        return view('admin.users.show', [
            'user' => $user, 'plans' => $plans, 'usage' => $usage, 'dailyFeatures' => $dailyFeatures,
            'allRoles' => Role::all(),
            'packages' => Package::with('role')->where('status', 'active')->orderBy('role_id')->orderBy('display_order')->get(),
            'credits' => (int) ContactCredit::where('user_id', $user->id)->value('balance'),
            'history' => Subscription::with('package')->where('user_id', $user->id)->latest()->limit(10)->get(),
        ]);
    }

    public function status(Request $request, User $user)
    {
        $data = $request->validate(['status' => ['required', 'in:active,restricted,suspended']]);
        $old = $user->status;
        $user->update(['status' => $data['status']]);
        $this->audit->log('user.status', $user, ['status' => $old], ['status' => $data['status']]);

        return back()->with('success', "Account is now {$data['status']}.");
    }

    public function addRole(Request $request, User $user, RoleService $roles)
    {
        $data = $request->validate(['role' => ['required', 'exists:roles,slug']]);
        $roles->assign($user, $data['role']);
        $this->audit->log('user.role_added', $user, null, ['role' => $data['role']]);

        return back()->with('success', 'Role added.');
    }

    public function removeRole(User $user, Role $role, RoleService $roles)
    {
        $roles->revoke($user, $role->slug);
        $this->audit->log('user.role_removed', $user, ['role' => $role->slug]);

        return back()->with('success', 'Role removed.');
    }

    public function grantSubscription(Request $request, User $user, SubscriptionService $subs)
    {
        $data = $request->validate(['package_id' => ['required', 'exists:packages,id']]);
        $package = Package::findOrFail($data['package_id']);

        if (! $user->roles()->where('roles.id', $package->role_id)->exists()) {
            return back()->withErrors(['package_id' => 'The user does not have the role this package belongs to.']);
        }

        $sub = $subs->subscribe($user, $package, null, 'admin', 0);
        $this->audit->log('user.subscription_granted', $user, null, ['package' => $package->name, 'subscription_id' => $sub->id]);

        return back()->with('success', "{$package->name} granted.");
    }

    public function grantBonus(Request $request, User $user, UsageTrackingService $usage)
    {
        $data = $request->validate([
            'feature_key' => ['required', 'exists:package_features,key'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);
        $usage->grantBonus($user, $data['feature_key'], $data['amount'], null, auth('admin')->id());
        $this->audit->log('user.bonus_granted', $user, null, $data);

        return back()->with('success', 'Bonus allowance added for today.');
    }

    public function resetUsage(Request $request, User $user, UsageTrackingService $usage)
    {
        $data = $request->validate(['feature_key' => ['nullable', 'exists:package_features,key']]);
        $usage->resetUsage($user, $data['feature_key'] ?? null, null, auth('admin')->id());
        $this->audit->log('user.usage_reset', $user, null, $data);

        return back()->with('success', "Today's usage reset.");
    }

    public function grantCredits(Request $request, User $user, ContactCreditService $credits)
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1', 'max:1000']]);
        $credits->add($user, $data['amount'], 'bonus', 'admin:'.auth('admin')->id());
        $this->audit->log('user.credits_granted', $user, null, $data);

        return back()->with('success', 'Contact credits added.');
    }
}
