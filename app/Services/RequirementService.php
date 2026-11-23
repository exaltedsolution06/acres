<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Requirement;
use App\Models\RequirementResponse;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequirementService
{
    public function __construct(
        private PermissionService $permissions,
        private UsageTrackingService $usage,
        private RequirementMatcher $matcher,
        private Notifier $notifier,
    ) {
    }

    /** Post a requirement as a tenant or buyer. Needs the plan's requirement_posting feature. */
    public function create(User $user, array $data): Requirement
    {
        $role = $data['role'];

        $this->permissions->assert($user, 'requirement_posting', $role);

        $active = Requirement::open()->where('user_id', $user->id)->count();

        if ($active >= config('engagement.max_active_requirements')) {
            throw ValidationException::withMessages(['title' => 'You can have '.config('engagement.max_active_requirements').' active requirements at a time. Close one first.']);
        }

        $requirement = Requirement::create($data + [
            'user_id' => $user->id, 'status' => 'active',
            'expires_at' => now()->addDays(config('engagement.requirement_validity_days')),
        ]);

        $this->matcher->matchRequirement($requirement);

        return $requirement;
    }

    public function close(Requirement $requirement): void
    {
        $requirement->update(['status' => 'closed']);
    }

    /** An owner or broker answers a requirement, optionally offering one of their live listings. */
    public function respond(User $poster, Requirement $requirement, ?Property $property, string $message): RequirementResponse
    {
        $role = $this->permissions->roleSlug($poster);

        if (! in_array($role, ['owner', 'broker'], true)) {
            throw ValidationException::withMessages(['message' => 'Switch to your Owner or Broker role to respond.']);
        }

        $this->permissions->assert($poster, 'requirement_posting', $role);

        if (! $requirement->isOpen()) {
            throw ValidationException::withMessages(['message' => 'This requirement is no longer open.']);
        }

        if ($requirement->user_id === $poster->id) {
            throw ValidationException::withMessages(['message' => 'You cannot respond to your own requirement.']);
        }

        if ($property && ($property->user_id !== $poster->id || ! $property->isVisible() || $property->purpose !== $requirement->purpose)) {
            throw ValidationException::withMessages(['property_id' => 'Choose one of your live listings with the same purpose.']);
        }

        if (RequirementResponse::where('requirement_id', $requirement->id)->where('user_id', $poster->id)->exists()) {
            throw ValidationException::withMessages(['message' => 'You have already responded to this requirement.']);
        }

        $this->usage->consume($poster, 'daily_enquiries', $role, 'requirement', $requirement->id);

        $response = $requirement->responses()->create([
            'user_id' => $poster->id, 'property_id' => $property?->id, 'message' => $message,
        ]);

        $this->notifier->send($requirement->user, 'requirement.response', 'Someone responded to your requirement',
            "{$poster->name} responded to \"{$requirement->title}\".", route('requirements.show', $requirement));

        return $response;
    }

    public function hasResponded(User $user, Requirement $requirement): bool
    {
        return RequirementResponse::where('requirement_id', $requirement->id)->where('user_id', $user->id)->exists();
    }
}
