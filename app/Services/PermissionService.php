<?php

namespace App\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\User;
use InvalidArgumentException;

/**
 * Single place that answers "what may this user do?" from the database-driven
 * package configuration. No subscription conditions elsewhere in the code.
 */
class PermissionService
{
    public function __construct(private SubscriptionService $subscriptions)
    {
    }

    public function roleSlug(User $user, ?string $role = null): string
    {
        $slug = $role ?? $user->activeRoleSlug();

        if (! $slug) {
            throw new FeatureNotAvailableException('role', 'Please select a role to continue.');
        }

        return $slug;
    }

    /** @return array{0: PackageFeature, 1: ?string, 2: bool} feature, raw value, unlimited */
    private function resolve(User $user, string $key, ?string $role): array
    {
        $feature = PackageFeature::where('key', $key)->where('is_active', true)->first();

        if (! $feature) {
            throw new InvalidArgumentException("Unknown feature key [{$key}].");
        }

        $package = $this->subscriptions->packageFor($user, $this->roleSlug($user, $role));
        $row = $package
            ? PackageFeatureValue::where('package_id', $package->id)->where('feature_id', $feature->id)->first()
            : null;

        return $row
            ? [$feature, $row->value, (bool) $row->is_unlimited]
            : [$feature, $feature->default_value, false];
    }

    public function can(User $user, string $key, ?string $role = null): bool
    {
        [$feature, $value, $unlimited] = $this->resolve($user, $key, $role);

        if (! $user->isActive()) {
            return false;
        }

        return match ($feature->type) {
            'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes'], true),
            'limit' => $unlimited || (int) $value > 0,
            default => $value !== null && $value !== '' && $value !== 'hidden',
        };
    }

    /** Numeric allowance. null means unlimited; 0 means none. */
    public function limit(User $user, string $key, ?string $role = null): ?int
    {
        [, $value, $unlimited] = $this->resolve($user, $key, $role);

        if (! $user->isActive()) {
            return 0;
        }

        return $unlimited ? null : (int) $value;
    }

    /** Raw value, used for enum features such as contact_visibility. */
    public function value(User $user, string $key, ?string $role = null): ?string
    {
        [, $value] = $this->resolve($user, $key, $role);

        return $user->isActive() ? $value : null;
    }

    public function assert(User $user, string $key, ?string $role = null): void
    {
        if (! $this->can($user, $key, $role)) {
            throw new FeatureNotAvailableException($key);
        }
    }
}
