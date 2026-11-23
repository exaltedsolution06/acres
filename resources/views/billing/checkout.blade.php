@extends('layouts.app')
@section('title', 'Checkout')
@section('content')
@php $inr = fn ($p) => '₹'.number_format($p / 100, 2); @endphp
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm"><div class="card-header">Order summary</div><div class="card-body">
            <p class="mb-3">{{ $quote['description'] }}</p>
            <table class="table table-sm mb-0">
                <tr><td>Price</td><td class="text-end">{{ $inr($quote['subtotal']) }}</td></tr>
                @if($quote['discount'] > 0)<tr class="text-success"><td>Discount @if($quote['coupon'])({{ $quote['coupon']->code }})@endif</td><td class="text-end">− {{ $inr($quote['discount']) }}</td></tr>@endif
                <tr><td>GST ({{ rtrim(rtrim(number_format($quote['gst'], 2), '0'), '.') }}%)</td><td class="text-end">{{ $inr($quote['tax']) }}</td></tr>
                <tr class="fw-semibold"><td>Total</td><td class="text-end">{{ $inr($quote['total']) }}</td></tr>
            </table>
            @if(! empty($quote['meta']['renewal']))<div class="small text-success mt-2">Renewal: your remaining days are kept and the new period is added after them.</div>@endif
        </div></div>
        <form method="GET" action="{{ route('checkout.show', [$purpose, $itemId]) }}" class="d-flex gap-2 mt-3">
            @if($property)<input type="hidden" name="property" value="{{ $property->public_id }}">@endif
            <input name="coupon" value="{{ $coupon }}" class="form-control form-control-sm" placeholder="Coupon code">
            <button class="btn btn-sm btn-outline-secondary">Apply</button>
        </form>
        @if(session('couponError'))<div class="small text-danger mt-1">{{ session('couponError') }}</div>@endif
    </div>
    <div class="col-lg-6">
        <form method="POST" action="{{ route('checkout.store') }}" class="card card-body shadow-sm row g-3 mx-0">@csrf
            <input type="hidden" name="purpose" value="{{ $purpose }}"><input type="hidden" name="item_id" value="{{ $itemId }}">
            @if($property)<input type="hidden" name="property" value="{{ $property->public_id }}">@endif
            <input type="hidden" name="coupon" value="{{ session('couponError') ? '' : $coupon }}">
            <div class="col-12"><h2 class="h6 mb-0">Billing details (for your GST invoice)</h2></div>
            <div class="col-12"><label class="form-label">Name on invoice</label><input name="billing_name" class="form-control" value="{{ old('billing_name', auth()->user()->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">State</label>
                <select name="billing_state" class="form-select" required><option value="">Choose</option>@foreach($states as $s)<option @selected(old('billing_state') === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">GSTIN (optional)</label><input name="gstin" class="form-control" value="{{ old('gstin') }}" maxlength="15" placeholder="22AAAAA0000A1Z5"></div>
            <div class="col-12"><button class="btn btn-primary w-100">{{ $quote['total'] === 0 ? 'Activate now' : 'Continue to payment' }}</button></div>
        </form>
    </div>
</div>
@endsection
