@extends('layouts.app')
@section('title', 'Your profile')
@section('content')
<div class="row g-4">
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header">Profile</div><div class="card-body">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="row g-3">@csrf
            <div class="col-12 d-flex align-items-center gap-3">
                @if($profile->photoUrl())<img src="{{ $profile->photoUrl() }}" alt="Profile photo" class="rounded-circle" style="width:64px;height:64px;object-fit:cover">@endif
                <div class="flex-grow-1"><label class="form-label">Photo (JPG, PNG or WebP, max 2 MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm"></div>
            </div>
            <div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Email</label>
                @if($user->email)<input class="form-control" value="{{ $user->email }}" disabled>
                @else<input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="Add an email address">@endif</div>
            <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $profile->city) }}"></div>
            <div class="col-md-6"><label class="form-label">Preferred locations</label><input name="preferred_locations" class="form-control" value="{{ old('preferred_locations', implode(', ', $profile->preferred_locations ?? [])) }}" placeholder="Salt Lake, New Town"></div>
            @if($isBroker)
                <div class="col-12"><hr class="my-1"><div class="small text-muted">Broker details</div></div>
                <div class="col-md-4"><label class="form-label">Account type</label>
                    <select name="account_type" class="form-select">@foreach(['individual', 'agency'] as $t)<option value="{{ $t }}" @selected(old('account_type', $profile->account_type) === $t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
                <div class="col-md-5"><label class="form-label">Business name</label><input name="business_name" class="form-control" value="{{ old('business_name', $profile->business_name) }}"></div>
                <div class="col-md-3"><label class="form-label">Experience (yrs)</label><input type="number" min="0" max="80" name="experience_years" class="form-control" value="{{ old('experience_years', $profile->experience_years) }}"></div>
            @endif
            <div class="col-12"><button class="btn btn-primary">Save profile</button></div>
        </form>
    </div></div></div>

    <div class="col-lg-5"><div class="card shadow-sm"><div class="card-header">Mobile number</div><div class="card-body">
        @if($user->hasVerifiedMobile())<p class="mb-2">+91 {{ $user->mobile }} <span class="badge text-bg-success">verified</span></p>@endif
        <form method="POST" action="{{ route('profile.mobile.send') }}" class="d-flex gap-2 mb-3">@csrf
            <input name="mobile" inputmode="numeric" class="form-control form-control-sm" placeholder="{{ $user->mobile ? 'Change number' : 'Mobile number' }}" required>
            <button class="btn btn-sm btn-outline-primary text-nowrap">Send OTP</button></form>
        @if($pendingMobile)
            <form method="POST" action="{{ route('profile.mobile.verify') }}" class="d-flex gap-2">@csrf
                <input name="code" inputmode="numeric" maxlength="6" class="form-control form-control-sm" placeholder="6-digit code" required>
                <button class="btn btn-sm btn-primary">Verify</button></form>
        @endif
        <div class="form-text mt-2">Your number is shared with other users only according to the plan rules of the person viewing it.</div>
    </div></div></div>
</div>
@endsection
