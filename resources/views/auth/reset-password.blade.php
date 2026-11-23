@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<div class="mx-auto" style="max-width:420px"><div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h5 mb-3">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required></div>
        <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" required></div>
        <button class="btn btn-primary w-100">Update password</button>
    </form>
</div></div></div>
@endsection
