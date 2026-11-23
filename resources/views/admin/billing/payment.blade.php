@extends('layouts.admin')
@section('title', $payment->txn_id)
@section('content')
@php $inr = fn ($p) => '₹'.number_format($p / 100, 2); @endphp
<a href="{{ route('admin.payments.index') }}" class="small">&larr; Payments</a>
<h1 class="h4 my-2"><code>{{ $payment->txn_id }}</code> <span class="badge text-bg-secondary fs-6">{{ $payment->status }}</span></h1>
@if($payment->failure_reason)<div class="alert alert-danger">{{ $payment->failure_reason }}</div>@endif
<div class="row g-3">
    <div class="col-lg-6"><div class="card shadow-sm mb-3"><div class="card-body">
        <p class="mb-2">{{ $payment->description }}</p>
        <table class="table table-sm mb-0">
            <tr><td>Customer</td><td>{{ $payment->user->name }} ({{ $payment->user->email ?? $payment->user->mobile }})</td></tr>
            <tr><td>Price</td><td>{{ $inr($payment->subtotal_paise) }}</td></tr>
            <tr><td>Discount</td><td>{{ $inr($payment->discount_paise) }} @if($payment->coupon)({{ $payment->coupon->code }})@endif</td></tr>
            <tr><td>GST ({{ $payment->gst_percent }}%)</td><td>{{ $inr($payment->tax_paise) }}</td></tr>
            <tr><td><strong>Total</strong></td><td><strong>{{ $inr($payment->total_paise) }}</strong></td></tr>
            <tr><td>Gateway</td><td>{{ $payment->gateway }} · order {{ $payment->gateway_order_id }} · payment {{ $payment->gateway_payment_id }}</td></tr>
            <tr><td>Paid</td><td>{{ $payment->paid_at?->timezone(config('marketplace.timezone'))->format('d M Y H:i') }}</td></tr>
            <tr><td>Billing</td><td>{{ $payment->billing['name'] ?? '' }}, {{ $payment->billing['state'] ?? '' }} @if(! empty($payment->billing['gstin']))· {{ $payment->billing['gstin'] }}@endif</td></tr>
            <tr><td>Invoice</td><td>{{ $payment->invoice?->number }} @if($payment->creditNote)· credit note {{ $payment->creditNote->number }}@endif</td></tr>
        </table>
    </div></div>
    @if($payment->isPaid() && $payment->total_paise > 0)
        <div class="card shadow-sm"><div class="card-header">Refund in full</div><div class="card-body">
            <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" onsubmit="return confirm('Refund this payment in full? This takes back what was purchased.')">@csrf
                <textarea name="reason" rows="2" class="form-control mb-2" placeholder="Reason" required minlength="5"></textarea>
                <button class="btn btn-outline-danger">Refund {{ $inr($payment->total_paise) }}</button>
                <div class="form-text">Money returns via the gateway. The plan, credits or promotion is taken back and a credit note is issued.</div></form>
        </div></div>
    @endif
    @foreach($payment->refunds as $r)<div class="alert alert-light border mt-3 mb-0">Refund #{{ $r->id }}: <strong>{{ $r->status }}</strong> · {{ $r->reason }} @if($r->admin_note)<br><small>{{ $r->admin_note }}</small>@endif</div>@endforeach
    </div>
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Gateway log</div>
        <ul class="list-group list-group-flush small">@forelse($payment->logs as $log)<li class="list-group-item"><strong>{{ $log->event }}</strong> <span class="text-muted">{{ $log->created_at->timezone(config('marketplace.timezone'))->format('d M H:i:s') }}</span>
            <details><summary class="text-primary" style="cursor:pointer">payload</summary><pre class="mb-0" style="white-space:pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details></li>@empty<li class="list-group-item text-muted">No log entries.</li>@endforelse</ul></div></div>
</div>
@endsection
