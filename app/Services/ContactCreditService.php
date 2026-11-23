<?php

namespace App\Services;

use App\Models\ContactCredit;
use App\Models\ContactCreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContactCreditService
{
    public function balance(User $user): int
    {
        return (int) ContactCredit::where('user_id', $user->id)->value('balance');
    }

    /** type: purchase | bonus | adjust | refund. Purchases must come from a verified payment. */
    public function add(User $user, int $amount, string $type = 'purchase', ?string $reference = null): ContactCredit
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference) {
            DB::table('contact_credits')->insertOrIgnore([
                'user_id' => $user->id, 'balance' => 0, 'lifetime_purchased' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $credit = ContactCredit::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $credit->balance += $amount;
            if ($type === 'purchase') {
                $credit->lifetime_purchased += $amount;
            }
            $credit->save();

            ContactCreditTransaction::create([
                'user_id' => $user->id, 'type' => $type, 'amount' => $amount,
                'balance_after' => $credit->balance, 'reference' => $reference,
            ]);

            return $credit;
        });
    }

    /** Take credits back (refunds). Refuses when they have already been spent. */
    public function reverse(User $user, int $amount, ?string $reference = null): void
    {
        DB::transaction(function () use ($user, $amount, $reference) {
            $credit = ContactCredit::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $credit || $credit->balance < $amount) {
                throw \Illuminate\Validation\ValidationException::withMessages(['refund' => 'The contact credits from this pack have already been used.']);
            }

            $credit->balance -= $amount;
            $credit->lifetime_purchased = max(0, $credit->lifetime_purchased - $amount);
            $credit->save();

            ContactCreditTransaction::create([
                'user_id' => $user->id, 'type' => 'refund', 'amount' => -$amount,
                'balance_after' => $credit->balance, 'reference' => $reference,
            ]);
        });
    }

    /** Spend one credit. Returns false when the balance is empty. */
    public function spend(User $user, ?string $reference = null): bool
    {
        return DB::transaction(function () use ($user, $reference) {
            $credit = ContactCredit::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $credit || $credit->balance < 1) {
                return false;
            }

            $credit->decrement('balance');

            ContactCreditTransaction::create([
                'user_id' => $user->id, 'type' => 'spend', 'amount' => -1,
                'balance_after' => $credit->balance, 'reference' => $reference,
            ]);

            return true;
        });
    }
}
