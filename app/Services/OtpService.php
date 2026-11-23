<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private SmsManager $sms)
    {
    }

    /**
     * Rate limit, store a hashed code and send it. With $deliver=false the limits
     * still count but nothing is sent (used by login so unknown numbers look identical).
     */
    public function send(string $mobile, string $purpose, ?string $ip = null, bool $deliver = true): void
    {
        $mobileKey = "otp-mobile:{$mobile}";
        $ipKey = 'otp-ip:'.($ip ?? 'cli');

        if (RateLimiter::tooManyAttempts($mobileKey, config('sms.otp.sends_per_mobile'))
            || RateLimiter::tooManyAttempts($ipKey, config('sms.otp.sends_per_ip'))) {
            throw ValidationException::withMessages(['mobile' => 'Too many OTP requests. Please wait a few minutes and try again.']);
        }

        RateLimiter::hit($mobileKey, 600);
        RateLimiter::hit($ipKey, 3600);

        if (! $deliver) {
            return;
        }

        OtpCode::where('mobile', $mobile)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = (int) config('sms.otp.ttl_minutes');

        OtpCode::create([
            'mobile' => $mobile, 'purpose' => $purpose, 'ip' => $ip,
            'code_hash' => $this->hash($mobile, $purpose, $code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->sms->gateway()->send($mobile, config('app.name')." OTP: {$code}. Valid for {$ttl} minutes. Do not share it with anyone.");
    }

    public function verify(string $mobile, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('mobile', $mobile)->where('purpose', $purpose)->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= (int) config('sms.otp.max_attempts')) {
            return false;
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, $this->hash($mobile, $purpose, $code))) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function hash(string $mobile, string $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$mobile}|{$purpose}|{$code}", (string) config('app.key'));
    }
}
