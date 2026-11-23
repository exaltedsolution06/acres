<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Property;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Full refunds only. Money goes back through the gateway first; the purchased benefit is then taken back and a credit note issued. */
class RefundService
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private InvoiceService $invoices,
        private PromotionService $promotions,
        private ContactCreditService $credits,
        private Notifier $notifier,
    ) {
    }

    /** Why a customer cannot ask for a refund, or null when they can. */
    public function eligibilityProblem(Payment $payment): ?string
    {
        if (! $payment->isPaid() || $payment->total_paise <= 0) {
            return 'This payment cannot be refunded.';
        }

        if ($payment->purpose !== 'subscription' || ! Package::withTrashed()->where('id', $payment->item_id)->value('refund_eligible')) {
            return 'This purchase is not eligible for a refund request. Contact support if something went wrong.';
        }

        if ($payment->paid_at->lt(now()->subDays(config('payments.refund_window_days')))) {
            return 'The '.config('payments.refund_window_days').'-day refund window has passed.';
        }

        if ($payment->refunds()->whereIn('status', ['requested', 'processed'])->exists()) {
            return 'A refund has already been requested for this payment.';
        }

        return null;
    }

    public function request(User $user, Payment $payment, string $reason): Refund
    {
        abort_unless($payment->user_id === $user->id, 403);

        if ($problem = $this->eligibilityProblem($payment)) {
            throw ValidationException::withMessages(['refund' => $problem]);
        }

        return Refund::create(['payment_id' => $payment->id, 'requested_by' => $user->id, 'amount_paise' => $payment->total_paise, 'reason' => $reason, 'status' => 'requested']);
    }

    /** Admin refunds a payment directly, without a customer request. */
    public function refundNow(Payment $payment, string $reason): Refund
    {
        if (! $payment->isPaid()) {
            throw ValidationException::withMessages(['refund' => 'Only paid payments can be refunded.']);
        }

        if ($payment->refunds()->where('status', 'requested')->exists()) {
            throw ValidationException::withMessages(['refund' => 'Approve or reject the pending refund request instead.']);
        }

        $this->preflight($payment); // refuse before recording a refund that can never be processed

        return $this->approve(Refund::create(['payment_id' => $payment->id, 'amount_paise' => $payment->total_paise, 'reason' => $reason, 'status' => 'requested']));
    }

    public function approve(Refund $refund, ?string $note = null): Refund
    {
        $payment = $refund->payment()->with('user')->first();

        if ($refund->status !== 'requested' || ! $payment->isPaid()) {
            throw ValidationException::withMessages(['refund' => 'This refund can no longer be processed.']);
        }

        $this->preflight($payment);

        $gatewayRefundId = null;

        if ($payment->total_paise > 0 && $payment->gateway !== 'none') {
            try {
                $gatewayRefundId = $this->gateways->gateway($payment->gateway)->refund((string) $payment->gateway_payment_id, (int) $payment->total_paise, $refund->reason)['id'];
            } catch (Throwable $e) {
                $refund->update(['status' => 'failed', 'admin_note' => mb_substr($e->getMessage(), 0, 500)]);

                throw ValidationException::withMessages(['refund' => 'The gateway could not process the refund: '.$e->getMessage()]);
            }
        }

        DB::transaction(function () use ($refund, $payment, $gatewayRefundId, $note) {
            $this->reverseBenefit($payment);
            $payment->forceFill(['status' => 'refunded'])->save();
            $refund->update(['status' => 'processed', 'gateway_refund_id' => $gatewayRefundId, 'processed_at' => now(), 'admin_note' => $note]);
            $this->invoices->issueCreditNote($payment, $payment->invoice);
        });

        $this->notifier->send($payment->user, 'refund.update', 'Refund processed', "{$payment->description}: {$payment->total()} has been refunded to your original payment method.", route('billing.index'));

        return $refund->fresh();
    }

    public function reject(Refund $refund, string $note): Refund
    {
        if ($refund->status !== 'requested') {
            throw ValidationException::withMessages(['refund' => 'This request was already handled.']);
        }

        $refund->update(['status' => 'rejected', 'admin_note' => $note]);

        if ($refund->requester) {
            $this->notifier->send($refund->requester, 'refund.update', 'Refund request declined', $note, route('billing.index'));
        }

        return $refund;
    }

    /** Check the benefit can be taken back before any money moves. */
    private function preflight(Payment $payment): void
    {
        if ($payment->purpose === 'credit_pack' && $this->credits->balance($payment->user) < (int) ($payment->meta['credits'] ?? 0)) {
            throw ValidationException::withMessages(['refund' => 'The contact credits from this pack have already been used, so it cannot be refunded automatically.']);
        }
    }

    private function reverseBenefit(Payment $payment): void
    {
        $meta = $payment->meta ?? [];

        switch ($payment->purpose) {
            case 'subscription':
                $subscription = Subscription::find($meta['subscription_id'] ?? 0);
                $package = Package::withTrashed()->find($payment->item_id);

                if (! $subscription) {
                    break;
                }

                if (! empty($meta['renewal']) && $subscription->ends_at) {
                    // A renewal only extended the plan: take the extra days back.
                    $ends = $subscription->ends_at->copy()->subDays((int) $package->validity_days);
                    $subscription->forceFill(['ends_at' => $ends->lt(now()) ? now() : $ends, 'grace_ends_at' => ($ends->lt(now()) ? now() : $ends)->copy()->addDays($package->grace_days)])->save();
                } else {
                    $subscription->forceFill(['status' => 'cancelled'])->save();
                }
                break;

            case 'credit_pack':
                $this->credits->reverse($payment->user, (int) $meta['credits'], $payment->txn_id);
                break;

            case 'promotion':
                Promotion::where('payment_id', $payment->id)->update(['status' => 'refunded']);

                if ($property = Property::find($payment->property_id)) {
                    $this->promotions->recompute($property);
                }
                break;
        }
    }
}
