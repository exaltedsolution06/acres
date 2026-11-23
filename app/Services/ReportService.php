<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private PropertyService $properties, private Notifier $notifier)
    {
    }

    public function create(User $reporter, Property $property, string $reason, ?string $details): PropertyReport
    {
        if (! array_key_exists($reason, config('trust.report_reasons'))) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
        }

        if ($property->user_id === $reporter->id) {
            throw ValidationException::withMessages(['reason' => 'You cannot report your own listing.']);
        }

        if (! $property->isVisible()) {
            throw ValidationException::withMessages(['reason' => 'This listing is no longer live.']);
        }

        if ($reason === 'other' && mb_strlen(trim((string) $details)) < 10) {
            throw ValidationException::withMessages(['details' => 'Please describe the problem (at least 10 characters).']);
        }

        if (PropertyReport::where('reporter_id', $reporter->id)->where('created_at', '>', now()->subDay())->count() >= config('trust.reports_per_user_per_day')) {
            throw ValidationException::withMessages(['reason' => 'You have sent many reports today. Please try again tomorrow.']);
        }

        if (PropertyReport::where('reporter_id', $reporter->id)->where('property_id', $property->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['reason' => 'You have already reported this listing. We are reviewing it.']);
        }

        return PropertyReport::create(['property_id' => $property->id, 'reporter_id' => $reporter->id, 'reason' => $reason, 'details' => $details, 'status' => 'open']);
    }

    public function dismiss(PropertyReport $report, ?string $note, ?int $adminId): void
    {
        $this->assertOpen($report);
        $report->update(['status' => 'dismissed', 'admin_note' => $note, 'handled_by' => $adminId, 'handled_at' => now()]);

        $this->tellReporter($report, 'We reviewed your report and found no breach of our rules. Thank you for flagging it.');
    }

    /** Mark the listing's open reports as actioned, optionally taking the listing down and suspending the poster. */
    public function action(PropertyReport $report, ?string $note, bool $takeDown, bool $suspendPoster, ?int $adminId): void
    {
        $this->assertOpen($report);
        $property = Property::withTrashed()->with('user')->findOrFail($report->property_id);

        $affected = DB::transaction(function () use ($report, $property, $note, $takeDown, $suspendPoster, $adminId) {
            $siblings = PropertyReport::where('property_id', $property->id)->where('status', 'open')->get();
            PropertyReport::whereIn('id', $siblings->pluck('id'))->update(['status' => 'actioned', 'admin_note' => $note, 'handled_by' => $adminId, 'handled_at' => now()]);

            if ($takeDown && in_array($property->status, [Property::PENDING, Property::PUBLISHED], true)) {
                $this->properties->reject($property, 'Removed after review of a report: '.$report->reasonLabel().'. Contact support if you think this is a mistake.', $adminId);
                $this->notifier->send($property->user, 'listing.reviewed', 'Your listing was removed', "\"{$property->title}\" was removed after a review. Contact support if you think this is a mistake.", route('listings.index'));
            }

            if ($suspendPoster) {
                $property->user->update(['status' => 'suspended']);
            }

            return $siblings;
        });

        foreach ($affected as $sibling) {
            $this->tellReporter($sibling, 'We reviewed your report and took action. Thank you for helping keep the marketplace trustworthy.');
        }
    }

    private function tellReporter(PropertyReport $report, string $message): void
    {
        if ($report->reporter) {
            $this->notifier->send($report->reporter, 'report.update', 'Update on your report', $message, route('home'));
        }
    }

    private function assertOpen(PropertyReport $report): void
    {
        if ($report->status !== 'open') {
            throw ValidationException::withMessages(['report' => 'This report was already handled.']);
        }
    }
}
