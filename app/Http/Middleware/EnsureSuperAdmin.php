<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Auth::guard('admin')->user()?->isSuper(), 403, 'Super admin access required.');

        return $next($request);
    }
}
