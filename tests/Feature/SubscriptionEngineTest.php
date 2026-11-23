<?php

namespace Tests\Feature;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\LimitExceededException;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ContactAccessService;
use App\Services\ContactCreditService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\SubscriptionService;
use App\Services\UsageTrackingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(array $roles = ['tenant'], ?string $mobile = null): User
    {
        $user = User::factory()->create(['mobile' => $mobile ?? '9'.random_int(100000000, 999999999), 'status' => 'active']);
        foreach ($roles as $role) {
            app(RoleService::class)->assign($user, $role);
        }

        return $user->fresh();
    }

    private function setFeature(string $packageSlug, string $key, ?string $value, bool $unlimited = false): Package
    {
        $package = Package::where('slug', $packageSlug)->firstOrFail();
        PackageFeatureValue::updateOrCreate(
            ['package_id' => $package->id, 'feature_id' => PackageFeature::where('key', $key)->value('id')],
            ['value' => $value, 'is_unlimited' => $unlimited],
        );

        return $package;
    }

    public function test_free_tenant_is_limited_to_two_interests_per_day(): void
    {
        $user = $this->makeUser();
        $usage = app(UsageTrackingService::class);

        $usage->consume($user, 'daily_interest');
        $usage->consume($user, 'daily_interest');

        $this->expectException(LimitExceededException::class);
        $usage->consume($user, 'daily_interest');
    }

    public function test_daily_limit_resets_at_midnight_asia_kolkata(): void
    {
        $user = $this->makeUser();
        $usage = app(UsageTrackingService::class);

        Carbon::setTestNow('2026-03-10 18:00:00 UTC'); // 23:30 IST
        $usage->consume($user, 'daily_interest');
        $usage->consume($user, 'daily_interest');

        try {
            $usage->consume($user, 'daily_interest');
            $this->fail('Third interest should have been blocked before midnight IST.');
        } catch (LimitExceededException) {
            $this->assertSame(0, $usage->remaining($user, 'daily_interest'));
        }

        Carbon::setTestNow('2026-03-10 18:31:00 UTC'); // 00:01 IST next day
        $this->assertTrue($usage->consume($user, 'daily_interest'));
        $this->assertSame(1, $usage->remaining($user, 'daily_interest'));
    }

    public function test_bonus_credits_extend_todays_allowance(): void
    {
        $user = $this->makeUser();
        $usage = app(UsageTrackingService::class);

        $usage->consume($user, 'daily_interest');
        $usage->consume($user, 'daily_interest');
        $usage->grantBonus($user, 'daily_interest', 2);

        $usage->consume($user, 'daily_interest');
        $usage->consume($user, 'daily_interest');

        $this->expectException(LimitExceededException::class);
        $usage->consume($user, 'daily_interest');
    }

    public function test_admin_reset_clears_counter_but_keeps_history(): void
    {
        $user = $this->makeUser();
        $usage = app(UsageTrackingService::class);

        $usage->consume($user, 'daily_interest');
        $usage->consume($user, 'daily_interest');
        $usage->resetUsage($user, 'daily_interest');

        $this->assertSame(0, $usage->usedToday($user, 'daily_interest'));
        $this->assertTrue($usage->consume($user, 'daily_interest'));
        $this->assertDatabaseHas('usage_logs', ['user_id' => $user->id, 'action' => 'reset']);
        $this->assertSame(2, \App\Models\UsageLog::where('user_id', $user->id)->where('action', 'consume')->count());
    }

    public function test_unique_property_views_do_not_consume_twice_on_same_day(): void
    {
        $user = $this->makeUser();
        $usage = app(UsageTrackingService::class);

        $this->assertTrue($usage->consume($user, 'daily_property_views', null, 'property', 55, true));
        $this->assertFalse($usage->consume($user, 'daily_property_views', null, 'property', 55, true));
        $this->assertSame(1, $usage->usedToday($user, 'daily_property_views'));
    }

    public function test_paid_subscription_raises_limits_and_expiry_falls_back_to_free(): void
    {
        $user = $this->makeUser();
        $permissions = app(PermissionService::class);

        $this->assertSame(2, $permissions->limit($user, 'daily_interest'));

        $sub = app(SubscriptionService::class)->subscribe($user, Package::where('slug', 'tenant-basic')->first(), null, 'admin');
        $this->assertSame(10, $permissions->limit($user, 'daily_interest'));

        Carbon::setTestNow($sub->ends_at->copy()->addDays(10)); // beyond the 3-day grace period
        $this->assertSame(2, $permissions->limit($user, 'daily_interest'));
        $this->assertSame(1, app(SubscriptionService::class)->expireDue());
        $this->assertSame('expired', Subscription::first()->status);
    }

    public function test_grace_period_keeps_paid_limits(): void
    {
        $user = $this->makeUser();
        $sub = app(SubscriptionService::class)->subscribe($user, Package::where('slug', 'tenant-basic')->first(), null, 'admin');

        Carbon::setTestNow($sub->ends_at->copy()->addDay());
        $this->assertSame(10, app(PermissionService::class)->limit($user, 'daily_interest'));
    }

    public function test_role_switching_does_not_reset_usage(): void
    {
        $user = $this->makeUser(['tenant', 'buyer']);
        $usage = app(UsageTrackingService::class);

        $usage->consume($user, 'daily_interest');
        app(RoleService::class)->switchTo($user, 'buyer');
        $usage->consume($user, 'daily_interest');

        $this->assertSame(2, $usage->usedToday($user, 'daily_interest'));
        $this->expectException(LimitExceededException::class);
        $usage->consume($user, 'daily_interest');
    }

    public function test_user_cannot_switch_to_an_unassigned_role(): void
    {
        $user = $this->makeUser(['tenant']);

        $this->expectException(AuthorizationException::class);
        app(RoleService::class)->switchTo($user, 'broker');
    }

    public function test_repeat_contact_view_is_free_when_unique_counting_is_on(): void
    {
        $this->setFeature('tenant-free', 'contact_visibility', 'on_click');
        $this->setFeature('tenant-free', 'daily_contact_views', '2');

        $viewer = $this->makeUser();
        $ownerA = $this->makeUser(['owner'], '9000000001');
        $ownerB = $this->makeUser(['owner'], '9000000002');
        $contacts = app(ContactAccessService::class);
        $usage = app(UsageTrackingService::class);

        $first = $contacts->reveal($viewer, $ownerA);
        $repeat = $contacts->reveal($viewer, $ownerA);

        $this->assertSame('9000000001', $first['mobile']);
        $this->assertSame('daily', $first['source']);
        $this->assertSame('already_unlocked', $repeat['source']);
        $this->assertSame(1, $usage->usedToday($viewer, 'daily_contact_views'));

        $contacts->reveal($viewer, $ownerB); // second unique contact uses the last allowance
        $this->assertSame(2, $usage->usedToday($viewer, 'daily_contact_views'));
    }

    public function test_contact_credits_are_used_after_daily_allowance_is_exhausted(): void
    {
        $this->setFeature('tenant-free', 'contact_visibility', 'on_click');
        $this->setFeature('tenant-free', 'daily_contact_views', '1');

        $viewer = $this->makeUser();
        $a = $this->makeUser(['owner'], '9000000011');
        $b = $this->makeUser(['owner'], '9000000012');
        $contacts = app(ContactAccessService::class);
        $credits = app(ContactCreditService::class);

        $contacts->reveal($viewer, $a);

        try {
            $contacts->reveal($viewer, $b);
            $this->fail('Should be blocked without credits.');
        } catch (LimitExceededException) {
        }

        $credits->add($viewer, 10, 'purchase', 'test-pack');
        $result = $contacts->reveal($viewer, $b);

        $this->assertSame('credit', $result['source']);
        $this->assertSame(9, $credits->balance($viewer));
    }

    public function test_masked_and_hidden_visibility_modes(): void
    {
        $viewer = $this->makeUser(); // tenant-free is 'masked'
        $owner = $this->makeUser(['owner'], '9876543210');
        $contacts = app(ContactAccessService::class);

        $masked = $contacts->reveal($viewer, $owner);
        $this->assertTrue($masked['masked']);
        $this->assertSame('98XXXXXX10', $masked['mobile']);

        $this->setFeature('tenant-free', 'contact_visibility', 'hidden');
        $this->expectException(FeatureNotAvailableException::class);
        $contacts->reveal($viewer, $owner);
    }

    public function test_after_interest_mode_requires_interest(): void
    {
        $this->setFeature('tenant-free', 'contact_visibility', 'after_interest');
        $this->setFeature('tenant-free', 'daily_contact_views', '5');

        $viewer = $this->makeUser();
        $owner = $this->makeUser(['owner'], '9000000021');
        $contacts = app(ContactAccessService::class);

        try {
            $contacts->reveal($viewer, $owner, null, false);
            $this->fail('Should require interest first.');
        } catch (FeatureNotAvailableException) {
        }

        $this->assertSame('9000000021', $contacts->reveal($viewer, $owner, null, true)['mobile']);
    }

    public function test_suspended_user_has_no_permissions_or_allowance(): void
    {
        $user = $this->makeUser();
        $user->update(['status' => 'suspended']);

        $this->assertFalse(app(PermissionService::class)->can($user, 'requirement_posting'));
        $this->assertSame(0, app(PermissionService::class)->limit($user, 'daily_property_views'));

        $this->expectException(LimitExceededException::class);
        app(UsageTrackingService::class)->consume($user, 'daily_property_views');
    }

    public function test_changing_a_package_value_in_the_database_changes_behaviour_without_code_changes(): void
    {
        $user = $this->makeUser();
        $this->setFeature('tenant-free', 'daily_interest', '5');

        $this->assertSame(5, app(PermissionService::class)->limit($user, 'daily_interest'));
    }
}
