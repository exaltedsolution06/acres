<?php

namespace App\Services;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SavedSearchService
{
    public function __construct(private PermissionService $permissions, private PropertySearch $search, private Notifier $notifier)
    {
    }

    public function save(User $user, string $name, array $filters): SavedSearch
    {
        $this->permissions->assert($user, 'saved_properties');

        if (SavedSearch::where('user_id', $user->id)->count() >= config('engagement.max_saved_searches')) {
            throw ValidationException::withMessages(['name' => 'You can keep '.config('engagement.max_saved_searches').' saved searches. Delete one first.']);
        }

        $clean = collect(array_intersect_key($filters, array_flip(PropertySearch::FILTER_KEYS)))
            ->reject(fn ($v) => $v === null || $v === '' || $v === [])->all();

        return SavedSearch::create(['user_id' => $user->id, 'name' => $name, 'filters' => $clean, 'last_checked_at' => now()]);
    }

    /** Scheduler: tell people about listings published since their search was last checked. */
    public function sendAlerts(): int
    {
        $sent = 0;

        SavedSearch::with('user')->each(function (SavedSearch $saved) use (&$sent) {
            $since = $saved->last_checked_at;
            $saved->update(['last_checked_at' => now()]);

            if (! $saved->user || ! $saved->user->isActive()) {
                return;
            }

            $query = $this->search->query($saved->filters)->where('properties.published_at', '>', $since);
            $count = (clone $query)->reorder()->count();

            if ($count === 0) {
                return;
            }

            $titles = $query->limit(3)->pluck('title')->implode('; ');
            $this->notifier->send($saved->user, 'search.alert', "{$count} new listing(s) for \"{$saved->name}\"",
                $titles, url('/search?'.http_build_query($saved->filters)));
            $sent++;
        });

        return $sent;
    }
}
