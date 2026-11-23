<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** Signed link from the verification email. */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        abort_unless($user->email && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('dashboard')->with('status', 'Your email address is verified.');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if (! $user->email || $user->hasVerifiedEmail()) {
            return back();
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent.');
    }
}
