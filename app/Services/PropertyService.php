<?php

namespace App\Services;

use App\Exceptions\LimitExceededException;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyService
{
    /** Allowed status changes. Approval publishes immediately (admin approve => published). */
    public const TRANSITIONS = [
        Property::DRAFT => [Property::PENDING, Property::ARCHIVED],
        Property::PENDING => [Property::PUBLISHED, Property::REJECTED, Property::ARCHIVED],
        Property::REJECTED => [Property::PENDING, Property::ARCHIVED],
        Property::PUBLISHED => [Property::PAUSED, Property::SOLD, Property::RENTED, Property::EXPIRED, Property::REJECTED, Property::PENDING, Property::ARCHIVED],
        Property::PAUSED => [Property::PUBLISHED, Property::SOLD, Property::RENTED, Property::EXPIRED, Property::ARCHIVED],
        Property::EXPIRED => [Property::PUBLISHED, Property::PENDING, Property::ARCHIVED],
        Property::SOLD => [],
        Property::RENTED => [],
        Property::ARCHIVED => [],
    ];

    public function __construct(private PermissionService $permissions)
    {
    }

    public function transition(Property $property, string $to, string $actorType = 'system', ?int $actorId = null, ?string $note = null): Property
    {
        $from = $property->status;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainException("A {$from} listing cannot become {$to}.");
        }

        $property->status = $to;
        $property->save();

        PropertyStatusHistory::create([
            'property_id' => $property->id, 'from_status' => $from, 'to_status' => $to,
            'actor_type' => $actorType, 'actor_id' => $actorId, 'note' => $note,
        ]);

        return $property;
    }

    public function canEdit(Property $property): bool
    {
        return ! in_array($property->status, [Property::SOLD, Property::RENTED, Property::ARCHIVED], true);
    }

    public function create(User $user, array $data, array $amenityIds = []): Property
    {
        $drafts = Property::where('user_id', $user->id)->where('status', Property::DRAFT)->count();

        if ($drafts >= config('property.max_drafts')) {
            throw ValidationException::withMessages(['title' => 'You have too many unfinished drafts. Submit or delete some first.']);
        }

        return DB::transaction(function () use ($user, $data, $amenityIds) {
            $property = new Property($data);
            $property->user_id = $user->id;
            $property->status = Property::DRAFT;
            $property->save();
            $property->amenities()->sync($amenityIds);

            PropertyStatusHistory::create([
                'property_id' => $property->id, 'from_status' => null, 'to_status' => Property::DRAFT,
                'actor_type' => 'user', 'actor_id' => $user->id, 'note' => 'Created',
            ]);

            return $property;
        });
    }

    public function update(Property $property, array $data, array $amenityIds = []): Property
    {
        if (! $this->canEdit($property)) {
            throw ValidationException::withMessages(['status' => 'This listing can no longer be edited.']);
        }

        return DB::transaction(function () use ($property, $data, $amenityIds) {
            $property->fill($data)->save();
            $property->amenities()->sync($amenityIds);

            if (config('property.reapprove_on_edit') && $property->status === Property::PUBLISHED) {
                $this->transition($property, Property::PENDING, 'user', $property->user_id, 'Edited, awaiting re-approval');
            }

            return $property;
        });
    }

    /** Owner submits a draft (or fixed rejected/expired listing) for moderation. */
    public function submit(Property $property): Property
    {
        $this->assertCanBecome($property, Property::PENDING);

        $problems = $this->completenessProblems($property);

        if ($problems) {
            throw ValidationException::withMessages(['submit' => $problems]);
        }

        $this->assertQuota($property);

        $liveStatuses = [Property::PENDING, Property::PUBLISHED, Property::PAUSED];
        $clones = Property::where('fingerprint', $property->fingerprint)->where('id', '!=', $property->id)->whereIn('status', $liveStatuses);

        if ((clone $clones)->where('user_id', $property->user_id)->exists()) {
            throw ValidationException::withMessages(['submit' => 'You already have a live listing with the same details and address.']);
        }

        $property->duplicate_of_id = (clone $clones)->where('user_id', '!=', $property->user_id)->orderBy('id')->value('id');

        return $this->transition($property, Property::PENDING, 'user', $property->user_id, 'Submitted for approval');
    }

    public function publish(Property $property, ?int $adminId = null): Property
    {
        $this->assertCanBecome($property, Property::PUBLISHED);

        $property->published_at = now();
        $property->expires_at = now()->addDays(config('property.listing_validity_days'));
        $property->priority = $this->priorityFor($property);
        $property->rejection_reason = null;

        $this->transition($property, Property::PUBLISHED, 'admin', $adminId, 'Approved and published');
        app(RequirementMatcher::class)->matchProperty($property);

        return $property;
    }

    public function reject(Property $property, string $reason, ?int $adminId = null): Property
    {
        $property->rejection_reason = $reason;

        return $this->transition($property, Property::REJECTED, 'admin', $adminId, $reason);
    }

    public function pause(Property $property): Property
    {
        return $this->transition($property, Property::PAUSED, 'user', $property->user_id, 'Paused by owner');
    }

    public function resume(Property $property): Property
    {
        if ($property->expires_at && $property->expires_at->isPast()) {
            throw ValidationException::withMessages(['status' => 'This listing has expired. Renew it instead.']);
        }

        return $this->transition($property, Property::PUBLISHED, 'user', $property->user_id, 'Resumed by owner');
    }

    public function markSold(Property $property): Property
    {
        if ($property->purpose !== 'sell') {
            throw ValidationException::withMessages(['status' => 'Only listings for sale can be marked as sold.']);
        }

        return $this->transition($property, Property::SOLD, 'user', $property->user_id, 'Marked as sold');
    }

    public function markRented(Property $property): Property
    {
        if ($property->purpose === 'sell') {
            throw ValidationException::withMessages(['status' => 'Only rental listings can be marked as rented.']);
        }

        return $this->transition($property, Property::RENTED, 'user', $property->user_id, 'Marked as rented');
    }

    public function archive(Property $property): Property
    {
        return $this->transition($property, Property::ARCHIVED, 'user', $property->user_id, 'Archived by owner');
    }

    /** Renew in the last renew_window_days, or after expiry. Validity is separate from subscription validity. */
    public function renew(Property $property): Property
    {
        $window = (int) config('property.renew_window_days');

        if ($property->status === Property::PUBLISHED) {
            if ($property->expires_at && now()->diffInDays($property->expires_at, false) > $window) {
                throw ValidationException::withMessages(['status' => "You can renew a listing in its last {$window} days."]);
            }
        } elseif ($property->status === Property::EXPIRED) {
            $this->assertQuota($property);
        } else {
            throw ValidationException::withMessages(['status' => 'Only published or expired listings can be renewed.']);
        }

        $base = $property->expires_at && $property->expires_at->isFuture() ? $property->expires_at : now();
        $property->expires_at = $base->copy()->addDays(config('property.listing_validity_days'));
        $property->priority = $this->priorityFor($property);

        if ($property->status === Property::EXPIRED) {
            $property->published_at = now();

            return $this->transition($property, Property::PUBLISHED, 'user', $property->user_id, 'Renewed after expiry');
        }

        $property->save();
        PropertyStatusHistory::create([
            'property_id' => $property->id, 'from_status' => Property::PUBLISHED, 'to_status' => Property::PUBLISHED,
            'actor_type' => 'user', 'actor_id' => $property->user_id, 'note' => 'Renewed',
        ]);

        return $property;
    }

    /** Scheduler entry point: mark published listings past their end date as expired. */
    public function expireDue(): int
    {
        $count = 0;

        Property::where('status', Property::PUBLISHED)->where('expires_at', '<', now())->each(function (Property $p) use (&$count) {
            $this->transition($p, Property::EXPIRED, 'system', null, 'Listing validity ended');
            $count++;
        });

        return $count;
    }

    public function activeCount(User $user, string $role, ?int $excludeId = null): int
    {
        return Property::where('user_id', $user->id)->where('posted_by', $role)
            ->whereIn('status', Property::QUOTA_STATUSES)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->count();
    }

    public function priorityFor(Property $property): int
    {
        $limit = $this->permissions->limit($property->user, 'search_priority', $property->posted_by);

        return $limit === null ? 9 : min(9, $limit);
    }

    /** @return string[] */
    public function completenessProblems(Property $property): array
    {
        $problems = [];

        if (! $property->address) {
            $problems[] = 'Add the full address (it is never shown publicly).';
        }

        if ($property->purpose === 'sell' ? ! $property->price : ! $property->monthly_rent) {
            $problems[] = $property->purpose === 'sell' ? 'Add the expected selling price.' : 'Add the monthly rent.';
        }

        $min = (int) config('property.min_photos');

        if ($property->images()->where('type', 'photo')->count() < $min) {
            $problems[] = "Upload at least {$min} photo".($min === 1 ? '' : 's').'.';
        }

        return $problems;
    }

    private function assertQuota(Property $property): void
    {
        $limit = $this->permissions->limit($property->user, 'active_listings', $property->posted_by);

        if ($limit !== null && $this->activeCount($property->user, $property->posted_by, $property->id) >= $limit) {
            throw new LimitExceededException('active_listings', $limit);
        }
    }

    private function assertCanBecome(Property $property, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$property->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "A {$property->status} listing cannot become {$to}."]);
        }
    }
}
