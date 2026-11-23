<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\RefundService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentAdminController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        return view('admin.billing.payments', [
            'payments' => $this->filtered($request)->with('user')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load(['user', 'invoice', 'creditNote', 'refunds', 'logs', 'coupon']);

        return view('admin.billing.payment', ['payment' => $payment]);
    }

    public function refund(Request $request, Payment $payment, RefundService $refunds)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $refunds->refundNow($payment, $data['reason']);
        $this->audit->log('payment.refunded', $payment, ['status' => 'paid'], ['status' => 'refunded', 'reason' => $data['reason']]);

        return back()->with('success', 'Refund processed and credit note issued.');
    }

    /** Revenue summary for a date range (India time). Revenue = paid payments; refunds are shown separately. */
    public function finance(Request $request)
    {
        [$from, $to] = $this->range($request);

        $inRange = fn () => Payment::whereIn('status', ['paid', 'refunded'])->whereBetween('paid_at', [$from, $to]);
        $net = (clone $inRange())->where('status', 'paid');
        $refunded = (clone $inRange())->where('status', 'refunded');

        return view('admin.billing.finance', [
            'from' => $from->copy()->timezone(config('marketplace.timezone')), 'to' => $to->copy()->timezone(config('marketplace.timezone')),
            'net' => (int) (clone $net)->sum('total_paise'),
            'taxable' => (int) (clone $net)->sum('subtotal_paise') - (int) (clone $net)->sum('discount_paise'),
            'tax' => (int) (clone $net)->sum('tax_paise'),
            'discounts' => (int) (clone $net)->sum('discount_paise'),
            'refundedTotal' => (int) (clone $refunded)->sum('total_paise'),
            'refundedCount' => (clone $refunded)->count(),
            'count' => (clone $net)->count(),
            'byPurpose' => (clone $net)->selectRaw('purpose, count(*) as n, sum(total_paise) as total')->groupBy('purpose')->get(),
        ]);
    }

    /** CSV for accountants and GST returns. */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date (IST)', 'Transaction', 'Invoice', 'Customer', 'Billing state', 'GSTIN', 'Purpose', 'Description', 'Taxable (INR)', 'CGST', 'SGST', 'IGST', 'Total (INR)', 'Status', 'Gateway payment id']);

            Payment::with(['user', 'invoice'])->whereIn('status', ['paid', 'refunded'])->whereBetween('paid_at', [$from, $to])->orderBy('paid_at')->each(function (Payment $p) use ($out) {
                $i = $p->invoice;
                fputcsv($out, [
                    $p->paid_at->timezone(config('marketplace.timezone'))->format('Y-m-d H:i'), $p->txn_id, $i?->number, $p->user->name,
                    $p->billing['state'] ?? '', $p->billing['gstin'] ?? '', $p->purpose, $this->safe($p->description),
                    $this->rupees($p->subtotal_paise - $p->discount_paise), $this->rupees($i?->cgst_paise ?? 0), $this->rupees($i?->sgst_paise ?? 0), $this->rupees($i?->igst_paise ?? 0),
                    $this->rupees($p->total_paise), $p->status, $p->gateway_payment_id,
                ]);
            });

            fclose($out);
        }, 'payments-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request)
    {
        return Payment::query()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->purpose, fn ($q, $v) => $q->where('purpose', $v))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('txn_id', 'like', "%{$term}%")->orWhere('gateway_payment_id', $term)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))));
    }

    /** @return array{0: Carbon, 1: Carbon} UTC bounds of the chosen India-time dates (default: this month). */
    private function range(Request $request): array
    {
        $tz = config('marketplace.timezone');
        $from = $request->filled('from') ? Carbon::parse($request->input('from'), $tz)->startOfDay() : now($tz)->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'), $tz)->endOfDay() : now($tz)->endOfDay();

        return [$from->utc(), $to->utc()];
    }

    private function rupees(int $paise): string
    {
        return number_format($paise / 100, 2, '.', '');
    }

    /** Stop spreadsheet formula injection from user-controlled text. */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
