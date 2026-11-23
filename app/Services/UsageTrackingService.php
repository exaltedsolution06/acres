<?php

namespace App\Services;

use App\Exceptions\LimitExceededException;
use App\Models\DailyUsageSummary;
use App\Models\Role;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Daily allowances are keyed to the ACCOUNT (not role, browser or device) and
 * reset at local midnight in config('marketplace.timezone').
 * The daily summary row is locked for update so concurrent requests cannot
 * both slip under the limit.
 */
class UsageTrackingService
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function today(): string
    {
        return now(config('marketplace.timezone'))->toDateString();
    }

    /**
     * Consume allowance for a daily feature key (e.g. daily_interest).
     *
     * @param  bool  $uniqueToday  when true, repeating the same subject on the same day is free
     * @return bool true if allowance was consumed, false if it was a free repeat
     *
     * @throws LimitExceededException
     */
    public function consume(
        User $user,
        string $key,
        ?string $role = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        bool $uniqueToday = false,
        int $quantity = 1,
    ): bool {
        $roleSlug = $this->permissions->roleSlug($user, $role);
        $limit = $this->permissions->limit($user, $key, $roleSlug);
        $roleId = Role::where('slug', $roleSlug)->value('id');
        $date = $this->today();

        return DB::transaction(function () use ($user, $key, $roleId, $subjectType, $subjectId, $uniqueToday, $quantity, $limit, $date) {
            $row = $this->lockedRow($user, $key, $date);

            if ($uniqueToday && $subjectType && $subjectId && UsageLog::query()
                ->where('user_id', $user->id)->where('feature_key', $key)->where('usage_date', $date)
                ->where('action', 'consume')->where('subject_type', $subjectType)->where('subject_id', $subjectId)
                ->exists()) {
                return false;
            }

            if ($limit !== null && $row->used + $quantity > $limit + $row->bonus) {
                throw new LimitExceededException($key, $limit);
            }

            $row->increment('used', $quantity);

            UsageLog::create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'feature_key' => $key,
                'action' => 'consume',
                'quantity' => $quantity,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'usage_date' => $date,
            ]);

            return true;
        });
    }

    public function usedToday(User $user, string $key): int
    {
        return (int) DailyUsageSummary::where('user_id', $user->id)
            ->where('feature_key', $key)->where('usage_date', $this->today())->value('used');
    }

    /** Remaining allowance today. null means unlimited. */
    public function remaining(User $user, string $key, ?string $role = null): ?int
    {
        $limit = $this->permissions->limit($user, $key, $role);

        if ($limit === null) {
            return null;
        }

        $row = DailyUsageSummary::where('user_id', $user->id)
            ->where('feature_key', $key)->where('usage_date', $this->today())->first();

        return max(0, $limit + (int) $row?->bonus - (int) $row?->used);
    }

    /** Admin: add bonus allowance for today (or a given local date). */
    public function grantBonus(User $user, string $key, int $amount, ?string $date = null, ?int $adminId = null): void
    {
        $date ??= $this->today();

        DB::transaction(function () use ($user, $key, $amount, $date, $adminId) {
            $row = $this->lockedRow($user, $key, $date);
            $row->increment('bonus', $amount);

            UsageLog::create([
                'user_id' => $user->id, 'feature_key' => $key, 'action' => 'bonus',
                'quantity' => $amount, 'usage_date' => $date, 'meta' => ['admin_id' => $adminId],
            ]);
        });
    }

    /** Admin: reset the day's counter(s). History in usage_logs is kept for reports. */
    public function resetUsage(User $user, ?string $key = null, ?string $date = null, ?int $adminId = null): void
    {
        $date ??= $this->today();

        DB::transaction(function () use ($user, $key, $date, $adminId) {
            $rows = DailyUsageSummary::where('user_id', $user->id)->where('usage_date', $date)
                ->when($key, fn ($q) => $q->where('feature_key', $key))->lockForUpdate()->get();

            foreach ($rows as $row) {
                UsageLog::create([
                    'user_id' => $user->id, 'feature_key' => $row->feature_key, 'action' => 'reset',
                    'quantity' => $row->used, 'usage_date' => $date, 'meta' => ['admin_id' => $adminId],
                ]);
                $row->update(['used' => 0]);
            }
        });
    }

    private function lockedRow(User $user, string $key, string $date): DailyUsageSummary
    {
        DB::table('daily_usage_summaries')->insertOrIgnore([
            'user_id' => $user->id, 'feature_key' => $key, 'usage_date' => $date,
            'used' => 0, 'bonus' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DailyUsageSummary::where('user_id', $user->id)
            ->where('feature_key', $key)->where('usage_date', $date)->lockForUpdate()->firstOrFail();
    }
}
