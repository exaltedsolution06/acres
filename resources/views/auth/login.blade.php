@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="mx-auto" style="max-width:480px">
    <h1 class="h4 mb-3">Log in</h1>
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#otp" type="button">Mobile OTP</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pw" type="button">Email & password</button></li>
    </ul>
    <div class="tab-content card card-body shadow-sm">
        <div class="tab-pane fade show active" id="otp">
            <form method="POST" action="{{ route('login.otp.send') }}">@csrf
                <div class="mb-3"><label class="form-label">Mobile number</label>
                    <div class="input-group"><span class="input-group-text">+91</span><input name="mobile" inputmode="numeric" class="form-control" value="{{ old('mobile') }}" required></div></div>
                <button class="btn btn-primary w-100">Send OTP</button>
            </form>
        </div>
        <div class="tab-pane fade" id="pw">
            <form method="POST" action="{{ route('login.submit') }}">@csrf
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Remember me</label></div>
                <button class="btn btn-primary w-100">Log in</button>
                <div class="text-center mt-2 small"><a href="{{ route('password.request') }}">Forgot password?</a></div>
            </form>
        </div>
    </div>
    <p class="text-center mt-3 small">New here? <a href="{{ route('register') }}">Create an account</a></p>
</div>
@endsection
