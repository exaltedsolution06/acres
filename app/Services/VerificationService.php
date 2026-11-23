<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Document-based verification. A badge says exactly what was checked and expires after a year.
 * Documents are kept on the private disk, only until the retention period after the decision.
 */
class VerificationService
{
    public function __construct(private Notifier $notifier)
    {
    }

    /** When the current verification of this kind ends, or null. */
    public function currentUntil(User $user, string $type, ?Property $property = null): ?Carbon
    {
        return match ($type) {
            'owner' => $user->owner_verified_until,
            'broker' => $user->broker_verified_until,
            'property_documents' => $property?->documents_reviewed_until,
            default => null,
        };
    }

    /** @param array<string, UploadedFile> $files keyed by document type */
    public function submit(User $user, string $type, array $files, ?Property $property = null, ?string $note = null): VerificationRequest
    {
        $config = config("trust.types.{$type}") ?? throw ValidationException::withMessages(['type' => 'Unknown verification type.']);

        if ($config['role'] && ! $user->hasRole($config['role'])) {
            throw ValidationException::withMessages(['type' => "Add the {$config['role']} role to your account first."]);
        }

        if ($type === 'property_documents') {
            if (! $property || $property->user_id !== $user->id || in_array($property->status, [Property::ARCHIVED, Property::SOLD, Property::RENTED], true)) {
                throw ValidationException::withMessages(['property' => 'Choose one of your own active listings.']);
            }
        } else {
            $property = null;
        }

        $until = $this->currentUntil($user, $type, $property);

        if ($until && $until->isFuture() && $until->gt(now()->addDays(config('trust.renew_window_days')))) {
            throw ValidationException::withMessages(['type' => 'You are already verified until '.$until->timezone(config('marketplace.timezone'))->format('d M Y').'.']);
        }

        $pending = VerificationRequest::where('user_id', $user->id)->where('type', $type)->where('property_id', $property?->id)->where('status', 'pending')->exists();

        if ($pending) {
            throw ValidationException::withMessages(['type' => 'You already have a pending request of this kind.']);
        }

        foreach ($config['required'] as $required) {
            if (empty($files[$required])) {
                throw ValidationException::withMessages(['documents' => 'Please upload: '.$config['documents'][$required].'.']);
            }
        }

        return DB::transaction(function () use ($user, $type, $files, $property, $note, $config) {
            $request = VerificationRequest::create(['user_id' => $user->id, 'type' => $type, 'property_id' => $property?->id, 'status' => 'pending', 'applicant_note' => $note]);

            foreach ($files as $docType => $file) {
                if (! $file instanceof UploadedFile || ! isset($config['documents'][$docType])) {
                    continue;
                }

                $request->documents()->create([
                    'doc_type' => $docType,
                    'path' => $file->store("verification/{$request->id}", 'private_documents'), // random name; never publicly reachable
                    'original_name' => Str::limit(preg_replace('/[^A-Za-z0-9._ -]/', '_', $file->getClientOriginalName()) ?: 'document', 100, ''),
                    'mime' => (string) $file->getMimeType(),
                    'size_kb' => (int) ceil($file->getSize() / 1024),
                ]);
            }

            return $request;
        });
    }

    public function approve(VerificationRequest $request, ?int $adminId = null): VerificationRequest
    {
        $this->assertStatus($request, 'pending');
        $until = now()->addDays(config('trust.validity_days'));

        DB::transaction(function () use ($request, $adminId, $until) {
            $request->update(['status' => 'approved', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'expires_at' => $until, 'reject_reason' => null]);
            $this->setFlag($request, $until);
        });

        $this->notifier->send($request->user, 'verification.reviewed', 'Verification approved: '.$request->label(),
            config("trust.types.{$request->type}.explain"), route('verification.index'));

        return $request;
    }

    public function reject(VerificationRequest $request, string $reason, ?int $adminId = null): VerificationRequest
    {
        $this->assertStatus($request, 'pending');
        $request->update(['status' => 'rejected', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'reject_reason' => $reason]);

        $this->notifier->send($request->user, 'verification.reviewed', 'Verification not approved: '.$request->label(), $reason, route('verification.index'));

        return $request;
    }

    /** Take back an approved verification (for example after fraud reports). */
    public function revoke(VerificationRequest $request, string $reason, ?int $adminId = null): VerificationRequest
    {
        $this->assertStatus($request, 'approved');

        DB::transaction(function () use ($request, $reason, $adminId) {
            $request->update(['status' => 'revoked', 'reviewed_by' => $adminId, 'reject_reason' => $reason]);
            $this->setFlag($request, null);
        });

        $this->notifier->send($request->user, 'verification.reviewed', 'Verification removed: '.$request->label(), $reason, route('verification.index'));

        return $request;
    }

    /** Scheduler: mark lapsed verifications as expired. Badges already stop showing on their end date. */
    public function expireDue(): int
    {
        $count = 0;

        VerificationRequest::where('status', 'approved')->where('expires_at', '<', now())->each(function (VerificationRequest $r) use (&$count) {
            $r->update(['status' => 'expired']);
            $this->setFlag($r, null, onlyIfPast: true);
            $count++;
        });

        return $count;
    }

    /** Scheduler: delete document files once the retention period after the decision has passed. */
    public function purgeDocuments(): int
    {
        $count = 0;
        $cutoff = now()->subDays(config('trust.document_retention_days'));

        VerificationDocument::whereHas('request', fn ($q) => $q->whereIn('status', ['approved', 'rejected', 'revoked', 'expired'])->where('reviewed_at', '<', $cutoff))
            ->each(function (VerificationDocument $d) use (&$count) {
                Storage::disk('private_documents')->delete($d->path);
                $d->delete();
                $count++;
            });

        return $count;
    }

    private function setFlag(VerificationRequest $request, ?Carbon $until, bool $onlyIfPast = false): void
    {
        $query = match ($request->type) {
            'owner' => User::whereKey($request->user_id)->when($onlyIfPast, fn ($q) => $q->where('owner_verified_until', '<', now())),
            'broker' => User::whereKey($request->user_id)->when($onlyIfPast, fn ($q) => $q->where('broker_verified_until', '<', now())),
            default => Property::whereKey($request->property_id)->when($onlyIfPast, fn ($q) => $q->where('documents_reviewed_until', '<', now())),
        };

        $column = match ($request->type) {
            'owner' => 'owner_verified_until', 'broker' => 'broker_verified_until', default => 'documents_reviewed_until',
        };

        $query->update([$column => $until]);
    }

    private function assertStatus(VerificationRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw ValidationException::withMessages(['status' => "This request is {$request->status}, not {$expected}."]);
        }
    }
}
