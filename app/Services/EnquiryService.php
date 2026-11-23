<?php

namespace App\Services;

use App\Exceptions\FeatureNotAvailableException;
use App\Models\Enquiry;
use App\Models\EnquiryNote;
use App\Models\Property;
use App\Models\SiteVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EnquiryService
{
    public function __construct(
        private UsageTrackingService $usage,
        private PermissionService $permissions,
        private Notifier $notifier,
    ) {
    }

    /** "Show interest": costs one daily_interest, and only once per property. */
    public function showInterest(User $seeker, Property $property): Enquiry
    {
        $this->assertCanContact($seeker, $property);

        return Cache::lock("enquiry:{$seeker->id}:{$property->id}", 10)->block(5, function () use ($seeker, $property) {
            $existing = Enquiry::where('property_id', $property->id)->where('from_user_id', $seeker->id)->first();

            if ($existing?->interest_at) {
                return $existing;
            }

            $this->usage->consume($seeker, 'daily_interest', null, 'property', $property->id);

            return $this->record($seeker, $property, ['interest_at' => now()], 'Interest shown');
        });
    }

    /** A written enquiry: costs one daily_enquiries, once per property. */
    public function sendEnquiry(User $seeker, Property $property, string $message): Enquiry
    {
        $this->assertCanContact($seeker, $property);

        return Cache::lock("enquiry:{$seeker->id}:{$property->id}", 10)->block(5, function () use ($seeker, $property, $message) {
            $existing = Enquiry::where('property_id', $property->id)->where('from_user_id', $seeker->id)->first();

            if ($existing?->enquired_at) {
                throw ValidationException::withMessages(['message' => 'You have already sent an enquiry for this property.']);
            }

            $this->usage->consume($seeker, 'daily_enquiries', null, 'property', $property->id);

            return $this->record($seeker, $property, ['message' => $message, 'enquired_at' => now()], 'Enquiry sent');
        });
    }

    public function hasShownInterest(User $user, Property $property): bool
    {
        return Enquiry::where('property_id', $property->id)->where('from_user_id', $user->id)->whereNotNull('interest_at')->exists();
    }

    /** Poster moves a lead through the pipeline. Everything beyond new/closed needs lead_management. */
    public function setStatus(Enquiry $enquiry, string $status, User $poster): Enquiry
    {
        if (! array_key_exists($status, config('engagement.statuses'))) {
            throw ValidationException::withMessages(['status' => 'Unknown status.']);
        }

        if (! in_array($status, config('engagement.free_statuses'), true)) {
            $this->requireLeadManagement($enquiry, $poster);
        }

        $enquiry->forceFill([
            'status' => $status,
            'closed_at' => in_array($status, config('engagement.closed_statuses'), true) ? now() : null,
            'last_activity_at' => now(),
        ])->save();

        return $enquiry;
    }

    public function closeBySeeker(Enquiry $enquiry): Enquiry
    {
        $enquiry->forceFill(['status' => 'closed', 'closed_at' => now(), 'last_activity_at' => now()])->save();

        return $enquiry;
    }

    public function addNote(Enquiry $enquiry, User $poster, string $body): EnquiryNote
    {
        $this->requireLeadManagement($enquiry, $poster);

        return $enquiry->notes()->create(['user_id' => $poster->id, 'body' => $body]);
    }

    public function setFollowUp(Enquiry $enquiry, User $poster, ?Carbon $at, ?string $note): Enquiry
    {
        $this->requireLeadManagement($enquiry, $poster);

        if ($at && $at->isPast()) {
            throw ValidationException::withMessages(['follow_up_at' => 'Choose a time in the future.']);
        }

        $enquiry->forceFill(['follow_up_at' => $at, 'follow_up_note' => $at ? $note : null])->save();

        return $enquiry;
    }

    // ---------- site visits ----------

    /** Seeker requests a visit; when the poster proposes one it is confirmed straight away. */
    public function requestVisit(Enquiry $enquiry, User $by, Carbon $when, ?string $note = null): SiteVisit
    {
        $isPoster = $by->id === $enquiry->to_user_id;

        if (! $isPoster && $by->id !== $enquiry->from_user_id) {
            throw new FeatureNotAvailableException('site_visit');
        }

        if ($enquiry->isClosed()) {
            throw ValidationException::withMessages(['scheduled_at' => 'This enquiry is closed.']);
        }

        if ($when->isPast() || $when->gt(now()->addDays(config('engagement.visit_max_days_ahead')))) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a time within the next '.config('engagement.visit_max_days_ahead').' days.']);
        }

        $visit = $enquiry->visits()->create([
            'requested_by' => $by->id, 'scheduled_at' => $when, 'note' => $note,
            'status' => $isPoster ? 'confirmed' : 'requested',
        ]);

        $this->touch($enquiry);
        $other = $isPoster ? $enquiry->seeker : $enquiry->poster;
        $this->notifier->send($other, 'visit.requested',
            $isPoster ? 'Site visit scheduled' : 'Site visit requested',
            ($isPoster ? 'A visit was scheduled' : $by->name.' asked to visit').' for '.$enquiry->property->title.' on '.$when->timezone(config('marketplace.timezone'))->format('d M Y, h:i A').'.',
            $isPoster ? route('enquiries.index') : route('leads.show', $enquiry));

        return $visit;
    }

    /** Poster: confirm, decline or complete. Either side: cancel. */
    public function respondToVisit(SiteVisit $visit, User $by, string $action): SiteVisit
    {
        $enquiry = $visit->enquiry;
        $isPoster = $by->id === $enquiry->to_user_id;

        if (! $isPoster && $by->id !== $enquiry->from_user_id) {
            throw new FeatureNotAvailableException('site_visit');
        }

        $allowed = match ($action) {
            'confirm' => $isPoster && $visit->status === 'requested' ? 'confirmed' : null,
            'decline' => $isPoster && $visit->status === 'requested' ? 'declined' : null,
            'complete' => $isPoster && $visit->status === 'confirmed' ? 'completed' : null,
            'cancel' => in_array($visit->status, ['requested', 'confirmed'], true) ? 'cancelled' : null,
            default => null,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['visit' => 'That action is not available for this visit.']);
        }

        $visit->update(['status' => $allowed]);
        $this->touch($enquiry);

        $other = $isPoster ? $enquiry->seeker : $enquiry->poster;
        $this->notifier->send($other, 'visit.requested', 'Site visit '.$allowed,
            'The visit for '.$enquiry->property->title.' on '.$visit->scheduled_at->timezone(config('marketplace.timezone'))->format('d M Y, h:i A').' is now '.$allowed.'.',
            $isPoster ? route('enquiries.index') : route('leads.show', $enquiry));

        return $visit;
    }

    // ---------- internals ----------

    private function record(User $seeker, Property $property, array $attributes, string $what): Enquiry
    {
        $enquiry = Enquiry::firstOrNew(['property_id' => $property->id, 'from_user_id' => $seeker->id]);
        $isNew = ! $enquiry->exists;

        $enquiry->fill($attributes + ['to_user_id' => $property->user_id, 'last_activity_at' => now()]);

        if ($enquiry->exists && $enquiry->isClosed()) { // a closed lead is reopened by new activity
            $enquiry->status = 'new';
            $enquiry->closed_at = null;
        }

        $enquiry->save();

        $this->notifier->send($property->user, 'enquiry.received',
            $isNew ? 'New enquiry on your listing' : 'Update on an enquiry',
            "{$seeker->name}: {$what} for \"{$property->title}\".",
            route('leads.show', $enquiry));

        return $enquiry;
    }

    private function touch(Enquiry $enquiry): void
    {
        $enquiry->forceFill(['last_activity_at' => now()])->save();
    }

    private function assertCanContact(User $seeker, Property $property): void
    {
        if (! $property->isVisible()) {
            throw ValidationException::withMessages(['property' => 'This listing is no longer available.']);
        }

        if ($property->user_id === $seeker->id) {
            throw ValidationException::withMessages(['property' => 'You cannot enquire about your own listing.']);
        }

        if (! $seeker->isActive()) {
            throw new FeatureNotAvailableException('daily_enquiries', 'Your account cannot send enquiries.');
        }
    }

    private function requireLeadManagement(Enquiry $enquiry, User $poster): void
    {
        $this->permissions->assert($poster, 'lead_management', $enquiry->property->posted_by);
    }
}
