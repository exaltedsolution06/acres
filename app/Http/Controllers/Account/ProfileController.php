<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Mobile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private OtpService $otp)
    {
    }

    public function edit(Request $request)
    {
        $user = $request->user()->load('roles');

        return view('profile.edit', [
            'user' => $user,
            'profile' => $user->profile()->firstOrCreate([]),
            'isBroker' => $user->roles->contains('slug', 'broker'),
            'pendingMobile' => $request->session()->get('pending_mobile'),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [$user->email ? 'prohibited' : 'nullable', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'city' => ['nullable', 'string', 'max:80'],
            'preferred_locations' => ['nullable', 'string', 'max:600'],
            'account_type' => ['nullable', 'in:individual,agency'],
            'business_name' => ['nullable', 'string', 'max:120'],
            'experience_years' => ['nullable', 'integer', 'between:0,80'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        $user->name = $data['name'];

        if (! $user->email && ! empty($data['email'])) {
            $user->email = $data['email'];
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        $profile = $user->profile()->firstOrCreate([]);
        $locations = collect(explode(',', (string) ($data['preferred_locations'] ?? '')))
            ->map(fn ($l) => trim($l))->filter()->unique()->take(10)->values()->all();

        $fields = [
            'city' => $data['city'] ?? null,
            'preferred_locations' => $locations,
        ];

        if ($user->hasRole('broker')) {
            $fields += [
                'account_type' => $data['account_type'] ?? 'individual',
                'business_name' => $data['business_name'] ?? null,
                'experience_years' => $data['experience_years'] ?? null,
            ];
        }

        if ($request->hasFile('photo')) {
            // store() generates a random file name; the user-supplied name is never used.
            $path = $request->file('photo')->store('profiles', 'uploads');
            if ($profile->photo_path) {
                Storage::disk('uploads')->delete($profile->photo_path);
            }
            $fields['photo_path'] = $path;
        }

        $profile->update($fields);

        return back()->with('status', 'Profile updated.');
    }

    public function sendMobileOtp(Request $request)
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:20']]);
        $mobile = Mobile::normalize($data['mobile']) ?? throw ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit Indian mobile number.']);

        if (User::withTrashed()->where('mobile', $mobile)->where('id', '!=', $request->user()->id)->exists()) {
            throw ValidationException::withMessages(['mobile' => 'This mobile number is already linked to another account.']);
        }

        $this->otp->send($mobile, 'verify_mobile', $request->ip());
        $request->session()->put('pending_mobile', $mobile);

        return back()->with('status', 'We sent a code to '.Mobile::mask($mobile).'.');
    }

    public function verifyMobile(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $mobile = $request->session()->get('pending_mobile');

        if (! $mobile || ! $this->otp->verify($mobile, 'verify_mobile', $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        try {
            $request->user()->forceFill(['mobile' => $mobile, 'mobile_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['mobile' => 'This mobile number is already linked to another account.']);
        }

        $request->session()->forget('pending_mobile');

        return back()->with('status', 'Mobile number verified.');
    }
}
