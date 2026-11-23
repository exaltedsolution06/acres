@extends('layouts.app')
@section('title', 'Billing')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Billing</h1><a href="{{ route('pricing') }}" class="btn btn-sm btn-primary">Plans and pricing</a></div>
<div class="vstack gap-3">
@forelse($payments as $p)
    <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div><div class="fw-semibold">{{ $p->description }}</div>
                <div class="small text-muted">{{ $p->paid_at->timezone(config('marketplace.timezone'))->format('d M Y, h:i A') }} · <code>{{ $p->txn_id }}</code></div></div>
            <div class="text-end"><div class="fs-5">{{ $p->total() }}</div>
                <span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : 'secondary' }}">{{ $p->status }}</span></div>
        </div>
        <div class="small mt-2 d-flex gap-3 flex-wrap">
            @if($p->invoice)<a href="{{ route('billing.invoice', $p) }}" target="_blank">Invoice {{ $p->invoice->number }}</a>@endif
            @if($p->creditNote)<a href="{{ route('billing.credit-note', $p) }}" target="_blank">Credit note {{ $p->creditNote->number }}</a>@endif
            @foreach($p->refunds as $r)<span class="text-muted">Refund {{ $r->status }}@if($r->admin_note && $r->status === 'rejected'): {{ $r->admin_note }}@endif</span>@endforeach
        </div>
        @if(in_array($p->id, $refundable))
            <details class="mt-2"><summary class="small text-primary" style="cursor:pointer">Request a refund</summary>
                <form method="POST" action="{{ route('billing.refund', $p) }}" class="mt-2">@csrf
                    <textarea name="reason" rows="2" class="form-control form-control-sm mb-2" minlength="10" maxlength="500" placeholder="Tell us why" required></textarea>
                    <button class="btn btn-sm btn-outline-secondary">Send request</button></form></details>
        @endif
    </div></div>
@empty
    <div class="alert alert-light border">No payments yet.</div>
@endforelse
</div>
<div class="mt-3">{{ $payments->links('pagination::bootstrap-5') }}</div>
@endsection
