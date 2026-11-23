<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\RoleService;
use App\Support\Mobile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __construct(private OtpService $otp, private RoleService $roles)
    {
    }

    public function show()
    {
        return view('auth.register', ['roles' => config('marketplace.roles')]);
    }

    /** Email + password registration. Mobile is optional here and verified later from the profile. */
    public function registerWithEmail(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:'.implode(',', config('marketplace.roles'))],
            'mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $mobile = null;
        if ($request->filled('mobile')) {
            $mobile = Mobile::normalize($data['mobile']) ?? throw ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit Indian mobile number.']);
            $this->assertMobileFree($mobile);
        }

        try {
            $user = DB::transaction(fn () => $this->createUser($data['name'], $data['roles'], [
                'email' => $data['email'], 'password' => $data['password'], 'mobile' => $mobile,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account with these details already exists.']);
        }

        $user->sendEmailVerificationNotification();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome! Please verify your email using the link we sent.');
    }

    /** Step 1 of mobile registration: collect details and send an OTP. */
    public function startMobile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'max:20'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['in:'.implode(',', config('marketplace.roles'))],
        ]);

        $mobile = Mobile::normalize($data['mobile']) ?? throw ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit Indian mobile number.']);
        $this->assertMobileFree($mobile);

        $this->otp->send($mobile, 'register', $request->ip());
        $request->session()->put('register', ['name' => $data['name'], 'mobile' => $mobile, 'roles' => $data['roles']]);

        return redirect()->route('register.verify');
    }

    public function showVerify(Request $request)
    {
        $reg = $request->session()->get('register');

        if (! $reg) {
            return redirect()->route('register');
        }

        return view('auth.verify-otp', [
            'heading' => 'Verify your mobile number',
            'mobile' => Mobile::mask($reg['mobile']),
            'action' => route('register.verify.submit'),
            'resend' => route('register.resend'),
        ]);
    }

    /** Step 2: verify the OTP, then create the account. */
    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $reg = $request->session()->get('register');

        if (! $reg) {
            return redirect()->route('register');
        }

        if (! $this->otp->verify($reg['mobile'], 'register', $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        try {
            $user = DB::transaction(fn () => $this->createUser($reg['name'], $reg['roles'], [
                'mobile' => $reg['mobile'], 'mobile_verified_at' => now(),
                'password' => Str::random(40), // OTP accounts sign in with a code; no usable password
            ]));
        } catch (UniqueConstraintViolationException) {
            $request->session()->forget('register');

            return redirect()->route('login')->withErrors(['mobile' => 'This mobile number is already registered. Please log in.']);
        }

        $request->session()->forget('register');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function resend(Request $request)
    {
        $reg = $request->session()->get('register');

        if (! $reg) {
            return redirect()->route('register');
        }

        $this->otp->send($reg['mobile'], 'register', $request->ip());

        return back()->with('status', 'A new code has been sent.');
    }

    private function assertMobileFree(string $mobile): void
    {
        if (User::withTrashed()->where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages(['mobile' => 'This mobile number is already registered. Please log in.']);
        }
    }

    private function createUser(string $name, array $roles, array $attributes): User
    {
        $user = User::create(['name' => $name] + $attributes);

        foreach (array_unique($roles) as $role) {
            $this->roles->assign($user, $role);
        }

        $user->profile()->create([]);

        return $user->fresh();
    }
}
