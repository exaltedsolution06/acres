@extends('layouts.app')
@section('title', 'Pay')
@push('head')<meta name="csrf-token" content="{{ csrf_token() }}">@endpush
@section('content')
<div class="mx-auto" style="max-width:520px"><div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h5">Complete your payment</h1>
    <p class="mb-1">{{ $payment->description }}</p>
    <div class="fs-3 fw-semibold mb-3">{{ $payment->total() }}</div>
    <div id="msg" class="small text-danger mb-2"></div>

    @if(! empty($checkout['fake']))
        <div class="alert alert-warning small">Test mode: no real money moves. This simulates the customer paying at the gateway.</div>
        <form method="POST" action="{{ route('checkout.simulate', $payment) }}">@csrf<button class="btn btn-success w-100">Simulate successful payment</button></form>
    @else
        <button id="pay" class="btn btn-primary w-100">Pay {{ $payment->total() }}</button>
        <div class="form-text text-center mt-2">Secure payment by Razorpay. Your plan activates as soon as the payment is confirmed.</div>
    @endif
</div></div></div>
@endsection
@push('scripts')
@if(empty($checkout['fake']))
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    var verifyUrl = @json(route('checkout.verify', $payment));
    var billingUrl = @json(route('billing.index'));
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var options = {
        key: @json($checkout['key'] ?? null), amount: @json($checkout['amount'] ?? null), currency: @json($checkout['currency'] ?? 'INR'),
        name: @json(config('app.name')), description: @json($payment->description), order_id: @json($payment->gateway_order_id),
        prefill: { name: @json($user->name), email: @json($user->email), contact: @json($user->mobile) },
        handler: function (r) {
            fetch(verifyUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ order_id: r.razorpay_order_id, payment_id: r.razorpay_payment_id, signature: r.razorpay_signature }) })
              .then(function (res) { return res.json(); })
              .then(function (d) { window.location = d.redirect || billingUrl; })
              .catch(function () { document.getElementById('msg').textContent = 'We could not confirm the payment yet. If money was deducted, your purchase activates automatically within a few minutes.'; });
        }
    };
    document.getElementById('pay').addEventListener('click', function () { new Razorpay(options).open(); });
})();
</script>
@endif
@endpush
