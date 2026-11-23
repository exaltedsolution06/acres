<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private OtpService $otp)
    {
    }

    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        return $this->finish($request, Auth::user());
    }

    /** Send a login OTP. The response is identical whether or not the number is registered. */
    public function sendOtp(Request $request)
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:20']]);
        $mobile = Mobile::normalize($data['mobile']) ?? throw ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit Indian mobile number.']);

        $this->otp->send($mobile, 'login', $request->ip(), deliver: User::where('mobile', $mobile)->exists());
        $request->session()->put('login_mobile', $mobile);

        return redirect()->route('login.otp');
    }

    public function showOtp(Request $request)
    {
        $mobile = $request->session()->get('login_mobile');

        if (! $mobile) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'heading' => 'Enter your login code',
            'mobile' => Mobile::mask($mobile),
            'action' => route('login.otp.verify'),
            'resend' => null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $mobile = $request->session()->get('login_mobile');

        if (! $mobile) {
            return redirect()->route('login');
        }

        $user = User::where('mobile', $mobile)->first();

        if (! $this->otp->verify($mobile, 'login', $request->input('code')) || ! $user) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        $request->session()->forget('login_mobile');
        Auth::login($user);

        return $this->finish($request, $user);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function finish(Request $request, User $user)
    {
        if ($user->status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => 'Your account has been suspended. Please contact support.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
