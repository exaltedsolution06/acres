<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->is_active) {
            Auth::guard('admin')->logout();

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('admin.login');
        }

        Auth::shouldUse('admin');

        return $next($request);
    }
}
