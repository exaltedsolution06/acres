<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(string $role = 'super_admin'): Admin
    {
        return Admin::create(['name' => 'Admin', 'email' => $role.'@example.com', 'password' => 'secret-pass-123', 'role' => $role, 'is_active' => true]);
    }

    private function marketUser(array $roles = ['tenant']): User
    {
        $user = User::factory()->create(['mobile' => '9'.random_int(100000000, 999999999)]);
        foreach ($roles as $r) {
            app(RoleService::class)->assign($user, $r);
        }

        return $user->fresh();
    }

    private function featurePayload(array $overrides = []): array
    {
        return PackageFeature::all()->mapWithKeys(function ($f) use ($overrides) {
            $value = $overrides[$f->key] ?? match ($f->type) {
                'boolean' => '0', 'limit' => '1', default => 'masked',
            };

            return [$f->id => is_array($value) ? $value : ['value' => $value]];
        })->all();
    }

    private function packagePayload(array $extra = []): array
    {
        return array_merge([
            'role_id' => Role::where('slug', 'tenant')->value('id'),
            'name' => 'Gold', 'price' => 999, 'gst_percent' => 18, 'validity_days' => 30,
            'grace_days' => 2, 'trial_days' => 0, 'display_order' => 9, 'status' => 'active',
        ], $extra);
    }

    public function test_guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/packages')->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_log_in_and_marketplace_users_cannot(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret-pass-123'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
        $this->assertGuest('web');
    }

    public function test_marketplace_user_session_does_not_open_admin(): void
    {
        $this->actingAs($this->marketUser())->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_staff_cannot_manage_packages_but_can_view_users(): void
    {
        $staff = $this->admin('staff');

        $this->actingAs($staff, 'admin')->get('/admin/packages')->assertForbidden();
        $this->actingAs($staff, 'admin')->get('/admin/users')->assertOk();
    }

    public function test_pages_render_for_super_admin(): void
    {
        $user = $this->marketUser(['tenant', 'broker']);
        $this->actingAs($this->admin(), 'admin');

        foreach (['/admin', '/admin/users', "/admin/users/{$user->id}", '/admin/packages', '/admin/packages/create',
            '/admin/features', '/admin/features/create', '/admin/audit-log'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin/packages/'.Package::first()->id.'/edit')->assertOk();
    }

    public function test_package_created_in_admin_drives_permissions_without_code_changes(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/packages', $this->packagePayload([
            'features' => $this->featurePayload([
                'daily_interest' => '7',
                'daily_property_views' => ['unlimited' => '1'],
                'map_search' => '1',
                'contact_visibility' => 'on_click',
            ]),
        ]))->assertRedirect(route('admin.packages.index'));

        $package = Package::where('name', 'Gold')->firstOrFail();
        $user = $this->marketUser();
        app(SubscriptionService::class)->subscribe($user, $package, null, 'admin');

        $permissions = app(PermissionService::class);
        $this->assertSame(7, $permissions->limit($user, 'daily_interest'));
        $this->assertNull($permissions->limit($user, 'daily_property_views'));
        $this->assertTrue($permissions->can($user, 'map_search'));
        $this->assertSame('on_click', $permissions->value($user, 'contact_visibility'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'package.created', 'subject_id' => $package->id]);
    }

    public function test_paid_package_requires_validity_and_free_default_flag_is_enforced(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/packages', $this->packagePayload(['validity_days' => null]))->assertSessionHasErrors('validity_days');
        $this->post('/admin/packages', $this->packagePayload(['is_default' => '1']))->assertSessionHasErrors('is_default');
    }

    public function test_editing_a_package_updates_limits_and_logs_the_change(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $package = Package::where('slug', 'tenant-basic')->first();

        $this->put("/admin/packages/{$package->id}", $this->packagePayload([
            'name' => 'Basic', 'price' => 249,
            'features' => $this->featurePayload(['daily_interest' => '33']),
        ]))->assertRedirect();

        $user = $this->marketUser();
        app(SubscriptionService::class)->subscribe($user, $package->fresh(), null, 'admin');
        $this->assertSame(33, app(PermissionService::class)->limit($user, 'daily_interest'));
        $this->assertSame('249.00', $package->fresh()->price);
        $this->assertTrue(AdminAuditLog::where('action', 'package.updated')->exists());
    }

    public function test_duplicate_creates_an_inactive_copy_with_the_same_feature_values(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $source = Package::where('slug', 'broker-agency')->first();

        $this->post("/admin/packages/{$source->id}/duplicate")->assertRedirect();

        $copy = Package::where('name', 'Copy of Agency')->firstOrFail();
        $this->assertSame('inactive', $copy->status);
        $this->assertFalse($copy->is_default);
        $this->assertSame($source->featureValues()->count(), $copy->featureValues()->count());
    }

    public function test_default_package_cannot_be_deleted_or_disabled_and_subscribed_packages_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $default = Package::where('slug', 'tenant-free')->first();

        $this->delete("/admin/packages/{$default->id}")->assertSessionHasErrors('package');
        $this->post("/admin/packages/{$default->id}/toggle")->assertSessionHasErrors('package');

        $basic = Package::where('slug', 'tenant-basic')->first();
        app(SubscriptionService::class)->subscribe($this->marketUser(), $basic, null, 'admin');
        $this->delete("/admin/packages/{$basic->id}")->assertSessionHasErrors('package');
        $this->assertNotNull($basic->fresh());
    }

    public function test_new_feature_appears_in_package_form_and_defaults_apply(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/features', [
            'key' => 'whatsapp_enquiry', 'name' => 'WhatsApp enquiry', 'type' => 'boolean', 'default_value' => '0', 'is_active' => '1',
        ])->assertRedirect();

        $this->get('/admin/packages/create')->assertSee('whatsapp_enquiry');
        $this->post('/admin/features', ['key' => 'Bad Key', 'name' => 'x', 'type' => 'boolean'])->assertSessionHasErrors('key');
    }

    public function test_suspending_a_user_removes_access_and_is_audited(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $user = $this->marketUser();

        $this->post("/admin/users/{$user->id}/status", ['status' => 'suspended'])->assertRedirect();

        $this->assertFalse(app(PermissionService::class)->can($user->fresh(), 'requirement_posting'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.status', 'subject_id' => $user->id]);
    }

    public function test_admin_can_grant_bonus_credits_and_reset_usage(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $user = $this->marketUser();
        $usage = app(UsageTrackingService::class);

        $usage->consume($user, 'daily_interest');
        $usage->consume($user, 'daily_interest');
        $this->post("/admin/users/{$user->id}/bonus", ['feature_key' => 'daily_interest', 'amount' => 3])->assertRedirect();
        $this->assertSame(3, $usage->remaining($user, 'daily_interest'));

        $this->post("/admin/users/{$user->id}/reset-usage", ['feature_key' => 'daily_interest'])->assertRedirect();
        $this->assertSame(0, $usage->usedToday($user, 'daily_interest'));

        $this->post("/admin/users/{$user->id}/credits", ['amount' => 10])->assertRedirect();
        $this->assertSame(10, app(\App\Services\ContactCreditService::class)->balance($user));
    }

    public function test_admin_can_grant_a_subscription_only_for_a_role_the_user_has(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $tenant = $this->marketUser(['tenant']);

        $this->post("/admin/users/{$tenant->id}/subscription", ['package_id' => Package::where('slug', 'broker-basic')->value('id')])
            ->assertSessionHasErrors('package_id');

        $this->post("/admin/users/{$tenant->id}/subscription", ['package_id' => Package::where('slug', 'tenant-premium')->value('id')])
            ->assertSessionHasNoErrors();
        $this->assertSame(25, app(PermissionService::class)->limit($tenant, 'daily_interest'));
    }

    public function test_removing_the_active_role_falls_back_to_another_role(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $user = $this->marketUser(['tenant', 'buyer']);
        $tenant = Role::where('slug', 'tenant')->first();

        $this->delete("/admin/users/{$user->id}/roles/{$tenant->id}")->assertRedirect();

        $this->assertSame('buyer', $user->fresh()->activeRoleSlug());
    }

    public function test_user_search_filters_by_role_and_status(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $broker = $this->marketUser(['broker']);
        $broker->update(['name' => 'Zed Broker Person']);
        $this->marketUser(['tenant'])->update(['name' => 'Plain Tenant']);

        $this->get('/admin/users?role=broker&q=Zed')->assertSee('Zed Broker Person')->assertDontSee('Plain Tenant');
    }
}
