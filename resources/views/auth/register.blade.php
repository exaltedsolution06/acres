@extends('layouts.app')
@section('title', 'Register')
@section('content')
<div class="mx-auto" style="max-width:560px">
    <h1 class="h4 mb-3">Create your account</h1>
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#by-mobile" type="button">Mobile & OTP</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#by-email" type="button">Email & password</button></li>
    </ul>
    <div class="tab-content card card-body shadow-sm">
        <div class="tab-pane fade show active" id="by-mobile">
            <form method="POST" action="{{ route('register.mobile') }}">@csrf
                <div class="mb-3"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
                <div class="mb-3"><label class="form-label">Mobile number</label>
                    <div class="input-group"><span class="input-group-text">+91</span><input name="mobile" inputmode="numeric" class="form-control" value="{{ old('mobile') }}" required></div></div>
                @include('auth.partials.roles', ['idPrefix' => 'm'])
                <button class="btn btn-primary w-100">Send OTP</button>
            </form>
        </div>
        <div class="tab-pane fade" id="by-email">
            <form method="POST" action="{{ route('register.email') }}">@csrf
                <div class="mb-3"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
                <div class="mb-3"><label class="form-label">Mobile (optional, verified later)</label><input name="mobile" inputmode="numeric" class="form-control" value="{{ old('mobile') }}"></div>
                <div class="row g-3 mb-3">
                    <div class="col-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Confirm</label><input type="password" name="password_confirmation" class="form-control" required></div>
                    <div class="form-text">At least 8 characters with letters and numbers.</div>
                </div>
                @include('auth.partials.roles', ['idPrefix' => 'e'])
                <button class="btn btn-primary w-100">Create account</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3 small">Already registered? <a href="{{ route('login') }}">Log in</a></p>
</div>
@endsection
