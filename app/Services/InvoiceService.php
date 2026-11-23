<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** GST invoices with gapless numbering per financial year (April to March), and credit notes for refunds. */
class InvoiceService
{
    /** Call inside the transaction that marks the payment paid, so a rollback also releases the number. */
    public function issueForPayment(Payment $payment): Invoice
    {
        return $this->create($payment, 'INV', 'invoice', null);
    }

    public function issueCreditNote(Payment $payment, ?Invoice $original): Invoice
    {
        return $this->create($payment, 'CN', 'credit_note', $original?->id);
    }

    private function create(Payment $payment, string $prefix, string $type, ?int $related): Invoice
    {
        $now = now();
        [$cgst, $sgst, $igst] = $this->splitTax($payment);

        return Invoice::create([
            'payment_id' => $payment->id,
            'type' => $type,
            'number' => $this->nextNumber($prefix, $now),
            'related_invoice_id' => $related,
            'issued_at' => $now,
            'billing' => ($payment->billing ?? []) + ['email' => $payment->user->email, 'mobile' => $payment->user->mobile],
            'lines' => [['description' => $payment->description, 'amount_paise' => (int) $payment->subtotal_paise]],
            'subtotal_paise' => $payment->subtotal_paise,
            'discount_paise' => $payment->discount_paise,
            'cgst_paise' => $cgst, 'sgst_paise' => $sgst, 'igst_paise' => $igst,
            'total_paise' => $payment->total_paise,
        ]);
    }

    /** @return array{0: int, 1: int, 2: int} CGST, SGST, IGST in paise */
    private function splitTax(Payment $payment): array
    {
        $tax = (int) $payment->tax_paise;
        $companyState = config('payments.company.state');
        $sameState = $companyState && ($payment->billing['state'] ?? null) === $companyState;

        if (! $sameState) {
            return [0, 0, $tax];
        }

        $cgst = intdiv($tax, 2);

        return [$cgst, $tax - $cgst, 0];
    }

    private function nextNumber(string $prefix, Carbon $when): string
    {
        $ist = $when->copy()->timezone(config('marketplace.timezone'));
        $startYear = $ist->month >= 4 ? $ist->year : $ist->year - 1;
        $fy = $startYear.'-'.substr((string) ($startYear + 1), -2);
        $series = "{$prefix}-{$fy}";

        DB::table('invoice_counters')->insertOrIgnore(['series' => $series, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('invoice_counters')->where('series', $series)->lockForUpdate()->first();
        $number = $row->last_number + 1;
        DB::table('invoice_counters')->where('series', $series)->update(['last_number' => $number, 'updated_at' => now()]);

        return "{$prefix}/{$fy}/".str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
