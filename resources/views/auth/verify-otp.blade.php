@extends('layouts.app')
@section('title', $heading)
@section('content')
<div class="mx-auto" style="max-width:420px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h5">{{ $heading }}</h1>
        <p class="text-muted small">We sent a 6-digit code to +91 {{ $mobile }}.</p>
        <form method="POST" action="{{ $action }}">@csrf
            <input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="form-control form-control-lg text-center mb-3" placeholder="······" required autofocus>
            <button class="btn btn-primary w-100">Verify</button>
        </form>
        @if($resend)
            <form method="POST" action="{{ $resend }}" class="text-center mt-2">@csrf<button class="btn btn-link btn-sm">Send a new code</button></form>
        @endif
    </div></div>
</div>
@endsection
