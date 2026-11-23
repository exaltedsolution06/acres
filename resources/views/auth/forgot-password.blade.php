@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<div class="mx-auto" style="max-width:420px"><div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h5 mb-3">Reset your password</h1>
    <form method="POST" action="{{ route('password.email') }}">@csrf
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
        <button class="btn btn-primary w-100">Email reset link</button>
    </form>
    <p class="small text-muted mt-3 mb-0">Registered with a mobile number only? Log in with an OTP instead.</p>
</div></div></div>
@endsection
