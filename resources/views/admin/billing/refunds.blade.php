@extends('layouts.admin')
@section('title', 'Refunds')
@section('content')
<h1 class="h4 mb-3">Refund requests</h1>
<div class="mb-3 d-flex gap-2">@foreach(['requested' => 'Pending', 'processed' => 'Processed', 'rejected' => 'Declined', 'failed' => 'Failed', 'all' => 'All'] as $k => $label)<a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.refunds.index', ['status' => $k]) }}">{{ $label }}</a>@endforeach</div>
<div class="vstack gap-3">
@forelse($refunds as $r)
    <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div><div class="fw-semibold">{{ $r->payment->description }}</div>
                <div class="small text-muted">{{ $r->payment->user->name }} · <a href="{{ route('admin.payments.show', $r->payment) }}">{{ $r->payment->txn_id }}</a> · {{ $r->created_at->diffForHumans() }}</div>
                <p class="mb-0 mt-2">{{ $r->reason }}</p>@if($r->admin_note)<p class="small text-muted mb-0">{{ $r->admin_note }}</p>@endif</div>
            <div class="text-end"><div class="fs-5">₹{{ number_format($r->amount_paise / 100, 2) }}</div><span class="badge text-bg-light border">{{ $r->status }}</span></div>
        </div>
        @if($r->status === 'requested')
            <div class="d-flex gap-2 mt-3 flex-wrap">
                <form method="POST" action="{{ route('admin.refunds.approve', $r) }}" onsubmit="return confirm('Refund this payment through the gateway?')">@csrf<button class="btn btn-sm btn-success">Approve and refund</button></form>
                <form method="POST" action="{{ route('admin.refunds.reject', $r) }}" class="d-flex gap-2">@csrf<input name="note" class="form-control form-control-sm" placeholder="Reason for declining" required minlength="5"><button class="btn btn-sm btn-outline-danger">Decline</button></form>
            </div>
        @endif
    </div></div>
@empty<div class="alert alert-light border">Nothing here.</div>@endforelse
</div>
<div class="mt-3">{{ $refunds->links('pagination::bootstrap-5') }}</div>
@endsection
