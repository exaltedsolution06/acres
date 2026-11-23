<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PromotionPackage;
use App\Services\RefundService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request, RefundService $refunds)
    {
        $payments = Payment::where('user_id', $request->user()->id)->whereIn('status', ['paid', 'refunded'])->with(['invoice', 'creditNote', 'refunds'])->latest('id')->paginate(15);

        return view('billing.index', [
            'payments' => $payments,
            'refundable' => $payments->getCollection()->filter(fn ($p) => $refunds->eligibilityProblem($p) === null)->pluck('id')->all(),
        ]);
    }

    public function invoice(Request $request, Payment $payment, string $type = 'invoice')
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        $invoice = $payment->invoices()->where('type', $type === 'credit-note' ? 'credit_note' : 'invoice')->firstOrFail();

        return view('billing.invoice', ['invoice' => $invoice, 'payment' => $payment, 'company' => config('payments.company')]);
    }

    public function requestRefund(Request $request, Payment $payment, RefundService $refunds)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $refunds->request($request->user(), $payment, $data['reason']);

        return back()->with('status', 'Refund requested. We will review it and let you know.');
    }

    public function promote(Request $request, Property $property)
    {
        abort_unless($property->user_id === $request->user()->id && $property->isVisible(), 404);

        return view('billing.promote', ['property' => $property, 'packages' => PromotionPackage::where('is_active', true)->orderBy('sort_order')->get()]);
    }
}
