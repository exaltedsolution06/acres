<?php

namespace App\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\LimitExceededException;
use App\Models\ContactView;
use App\Models\User;

/**
 * The only code path that may return a full phone number.
 * Visibility mode comes from the package feature `contact_visibility`:
 * hidden | masked | after_interest | on_click | credits | unlimited
 */
class ContactAccessService
{
    public function __construct(
        private PermissionService $permissions,
        private UsageTrackingService $usage,
        private ContactCreditService $credits,
    ) {
    }

    /** @return array{mobile: string, masked: bool, source: string} */
    public function reveal(User $viewer, User $target, ?int $propertyId = null, bool $hasInterest = false, ?string $ip = null): array
    {
        if ($viewer->is($target)) {
            return ['mobile' => (string) $target->mobile, 'masked' => false, 'source' => 'own'];
        }

        if (! $viewer->isActive()) {
            throw new FeatureNotAvailableException('contact_visibility', 'Your account cannot view contact details.');
        }

        if (! $target->mobile) {
            throw new FeatureNotAvailableException('contact_visibility', 'Contact details are not available for this user.');
        }

        $role = $this->permissions->roleSlug($viewer);
        $mode = $this->permissions->value($viewer, 'contact_visibility', $role) ?: 'hidden';

        if ($mode === 'hidden') {
            throw new FeatureNotAvailableException('contact_visibility');
        }

        if ($mode === 'masked') {
            return ['mobile' => $this->mask($target->mobile), 'masked' => true, 'source' => 'masked'];
        }

        if ($mode === 'after_interest' && ! $hasInterest) {
            throw new FeatureNotAvailableException('contact_visibility', 'Show interest in this property to view the contact number.');
        }

        $source = $mode === 'unlimited' ? 'unlimited' : $this->charge($viewer, $target, $role, $mode);

        ContactView::create([
            'viewer_id' => $viewer->id,
            'target_user_id' => $target->id,
            'property_id' => $propertyId,
            'mode' => $mode,
            'charged' => in_array($source, ['daily', 'credit'], true),
            'charge_source' => in_array($source, ['daily', 'credit'], true) ? $source : null,
            'ip' => $ip,
        ]);

        return ['mobile' => $target->mobile, 'masked' => false, 'source' => $source];
    }

    /** Daily allowance first, then purchased credits. Returns daily | credit | already_unlocked. */
    private function charge(User $viewer, User $target, string $role, string $mode): string
    {
        $unique = $this->permissions->can($viewer, 'contact_unique_counting', $role);

        if ($unique && ContactView::where('viewer_id', $viewer->id)
            ->where('target_user_id', $target->id)->where('charged', true)->exists()) {
            return 'already_unlocked';
        }

        $failure = null;

        if ($mode !== 'credits') {
            try {
                $this->usage->consume($viewer, 'daily_contact_views', $role, 'contact', $target->id);

                return 'daily';
            } catch (LimitExceededException $e) {
                $failure = $e;
            }
        }

        if ($this->credits->spend($viewer, "contact:{$target->id}")) {
            return 'credit';
        }

        throw $failure ?? new LimitExceededException('daily_contact_views', 0);
    }

    private function mask(string $mobile): string
    {
        $len = strlen($mobile);

        return $len <= 4 ? str_repeat('X', $len) : substr($mobile, 0, 2).str_repeat('X', $len - 4).substr($mobile, -2);
    }
}
