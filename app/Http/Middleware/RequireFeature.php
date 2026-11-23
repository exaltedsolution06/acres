<?php

namespace App\Http\Middleware;

use App\Exceptions\FeatureNotAvailableException;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Usage: Route::middleware([RequireFeature::class.':broker_crm'])
 */
class RequireFeature
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $featureKey)
    {
        $user = $request->user();

        if (! $user) {
            throw new FeatureNotAvailableException($featureKey, 'Please log in to continue.');
        }

        $this->permissions->assert($user, $featureKey);

        return $next($request);
    }
}
