<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RoleService;
use App\Services\Sms\ArraySmsGateway;
use App\Services\UsageTrackingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['sms.driver' => 'array']);
        ArraySmsGateway::$messages = [];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(array $attrs = [], array $roles = ['tenant']): User
    {
        $user = User::factory()->create($attrs + ['status' => 'active']);
        foreach ($roles as $role) {
            app(RoleService::class)->assign($user, $role);
        }

        return $user->fresh();
    }

    private function startMobileRegistration(string $mobile = '98765 43210', array $roles = ['tenant', 'owner'])
    {
        return $this->post('/register/mobile', ['name' => 'Asha Roy', 'mobile' => $mobile, 'roles' => $roles]);
    }

    // ---------- registration ----------

    public function test_mobile_registration_with_otp_creates_a_verified_multi_role_account(): void
    {
        $this->startMobileRegistration('+91 98765 43210')->assertRedirect(route('register.verify'));
        $code = ArraySmsGateway::lastCode('9876543210');
        $this->assertNotNull($code);

        $this->post('/register/verify', ['code' => $code])->assertRedirect(route('dashboard'));

        $user = User::where('mobile', '9876543210')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedMobile());
        $this->assertNull($user->email);
        $this->assertEqualsCanonicalizing(['tenant', 'owner'], $user->roles->pluck('slug')->all());
        $this->assertSame('tenant', $user->activeRoleSlug());
        $this->assertNotNull($user->profile);
    }

    public function test_otp_is_stored_hashed(): void
    {
        $this->startMobileRegistration();
        $code = ArraySmsGateway::lastCode();

        $this->assertDatabaseMissing('otp_codes', ['code_hash' => $code]);
        $this->assertSame(64, strlen(\App\Models\OtpCode::first()->code_hash));
    }

    public function test_an_existing_mobile_number_cannot_register_again(): void
    {
        $this->user(['mobile' => '9876543210']);

        $this->startMobileRegistration()->assertSessionHasErrors('mobile');
        $this->assertEmpty(ArraySmsGateway::$messages);
    }

    public function test_invalid_mobile_numbers_are_rejected(): void
    {
        $this->startMobileRegistration('12345')->assertSessionHasErrors('mobile');
    }

    public function test_wrong_codes_are_limited_to_five_attempts(): void
    {
        $this->startMobileRegistration();
        $real = ArraySmsGateway::lastCode();
        $wrong = $real === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register/verify', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->post('/register/verify', ['code' => $real])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_expired_codes_are_rejected(): void
    {
        $this->startMobileRegistration();
        $code = ArraySmsGateway::lastCode();

        Carbon::setTestNow(now()->addMinutes(6));
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_otp_requests_are_rate_limited_per_mobile(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->startMobileRegistration()->assertSessionHasNoErrors();
        }

        $this->startMobileRegistration()->assertSessionHasErrors('mobile');
        $this->assertCount(3, ArraySmsGateway::$messages);
    }

    public function test_email_registration_sends_verification_and_the_signed_link_verifies(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ravi Das', 'email' => 'ravi@example.com', 'password' => 'Passw0rd99', 'password_confirmation' => 'Passw0rd99',
            'roles' => ['broker', 'buyer'], 'mobile' => '09123456780',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'ravi@example.com')->firstOrFail();
        $this->assertSame('9123456780', $user->mobile);
        $this->assertNull($user->mobile_verified_at);
        $this->assertCount(2, $user->roles);
        Notification::assertSentTo($user, VerifyEmail::class);

        $good = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $bad = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => 'tampered']);

        $this->get($bad)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($good)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_registration_validates_password_email_and_roles(): void
    {
        $this->user(['email' => 'taken@example.com']);
        $base = ['name' => 'X', 'password' => 'Passw0rd99', 'password_confirmation' => 'Passw0rd99', 'roles' => ['tenant']];

        $this->post('/register', $base + ['email' => 'taken@example.com'])->assertSessionHasErrors('email');
        $this->post('/register', array_merge($base, ['email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', array_merge($base, ['email' => 'b@example.com', 'roles' => ['admin']]))->assertSessionHasErrors('roles.0');
        $this->post('/register', array_merge($base, ['email' => 'c@example.com', 'roles' => []]))->assertSessionHasErrors('roles');
    }

    // ---------- login ----------

    public function test_password_login_works_and_wrong_password_fails(): void
    {
        $user = $this->user(['email' => 'a@example.com', 'password' => 'Passw0rd99']);

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'Passw0rd99'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_is_throttled(): void
    {
        $this->user(['email' => 'a@example.com', 'password' => 'Passw0rd99']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'a@example.com', 'password' => 'Passw0rd99'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_accounts_cannot_log_in(): void
    {
        $this->user(['email' => 's@example.com', 'password' => 'Passw0rd99', 'status' => 'suspended']);

        $this->post('/login', ['email' => 's@example.com', 'password' => 'Passw0rd99'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspending_a_logged_in_user_ends_their_session_on_the_next_request(): void
    {
        $user = $this->user(['status' => 'suspended']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_otp_login_works_for_registered_numbers(): void
    {
        $user = $this->user(['mobile' => '9000000001']);

        $this->post('/login/otp', ['mobile' => '9000000001'])->assertRedirect(route('login.otp'));
        $code = ArraySmsGateway::lastCode('9000000001');

        $this->post('/login/otp/verify', ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_login_for_unknown_numbers_looks_the_same_but_sends_nothing(): void
    {
        $this->post('/login/otp', ['mobile' => '9000000009'])->assertRedirect(route('login.otp'));
        $this->assertEmpty(ArraySmsGateway::$messages);

        $this->post('/login/otp/verify', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'r@example.com', 'password' => 'OldPassw0rd']);

        $this->post('/forgot-password', ['email' => 'r@example.com'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'r@example.com', 'password' => 'NewPassw0rd', 'password_confirmation' => 'NewPassw0rd',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassw0rd', $user->fresh()->password));
    }

    // ---------- dashboards ----------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_the_plan_and_role_switching_keeps_usage(): void
    {
        $user = $this->user(['mobile' => '9000000002'], ['tenant', 'buyer']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('dashboard.role', 'tenant'));
        $this->get('/dashboard/tenant')->assertOk()->assertSee('Free')->assertSee('Daily interests');

        app(UsageTrackingService::class)->consume($user, 'daily_interest');

        $this->post('/role/switch', ['role' => 'buyer'])->assertRedirect(route('dashboard.role', 'buyer'));
        $this->assertSame('buyer', $user->fresh()->activeRoleSlug());
        $this->get('/dashboard/buyer')->assertOk()->assertSee('1 used');
    }

    public function test_a_user_cannot_open_or_switch_to_a_role_they_do_not_hold(): void
    {
        $user = $this->user([], ['tenant']);

        $this->actingAs($user)->get('/dashboard/broker')->assertForbidden();
        $this->post('/role/switch', ['role' => 'broker'])->assertForbidden();
        $this->post('/role/switch', ['role' => 'admin'])->assertSessionHasErrors('role');
    }

    // ---------- profile ----------

    public function test_profile_update_stores_a_photo_under_a_generated_name(): void
    {
        Storage::fake('uploads');
        $user = $this->user(['email' => 'p@example.com'], ['tenant']);

        $this->actingAs($user)->post('/profile', [
            'name' => 'New Name', 'city' => 'Kolkata', 'preferred_locations' => 'Salt Lake, New Town, Salt Lake',
            'photo' => UploadedFile::fake()->image('evil name.jpg', 300, 300),
            'business_name' => 'Ignored for non-brokers',
        ])->assertSessionHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('Kolkata', $profile->city);
        $this->assertSame(['Salt Lake', 'New Town'], $profile->preferred_locations);
        $this->assertStringStartsWith('profiles/', $profile->photo_path);
        $this->assertStringNotContainsString('evil', $profile->photo_path);
        Storage::disk('uploads')->assertExists($profile->photo_path);
        $this->assertNull($profile->business_name);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('uploads');
        $user = $this->user(['email' => 'p@example.com']);

        $this->actingAs($user)->post('/profile', [
            'name' => 'X', 'photo' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_broker_details_are_saved_for_brokers(): void
    {
        $user = $this->user(['email' => 'b@example.com'], ['broker']);

        $this->actingAs($user)->post('/profile', [
            'name' => 'Broker', 'account_type' => 'agency', 'business_name' => 'Prime Realty', 'experience_years' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertSame('agency', $user->fresh()->profile->account_type);
        $this->assertSame('Prime Realty', $user->fresh()->profile->business_name);
        $this->assertSame(7, $user->fresh()->profile->experience_years);
    }

    public function test_mobile_can_be_added_and_verified_from_the_profile_but_not_if_taken(): void
    {
        $user = $this->user(['email' => 'm@example.com']);
        $this->user(['mobile' => '9111111111', 'email' => 'other@example.com']);

        $this->actingAs($user)->post('/profile/mobile', ['mobile' => '9111111111'])->assertSessionHasErrors('mobile');

        $this->post('/profile/mobile', ['mobile' => '9222222222'])->assertSessionHasNoErrors();
        $this->post('/profile/mobile/verify', ['code' => ArraySmsGateway::lastCode('9222222222')])->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('9222222222', $fresh->mobile);
        $this->assertTrue($fresh->hasVerifiedMobile());
    }
}
