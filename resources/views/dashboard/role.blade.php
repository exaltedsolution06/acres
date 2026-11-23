@extends('layouts.app')
@section('title', ucfirst($role).' dashboard')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Hello, {{ $user->name }}</h1>
        <div class="small mt-1">
            <span class="badge text-bg-{{ $user->hasVerifiedMobile() ? 'success' : 'secondary' }}">Mobile {{ $user->hasVerifiedMobile() ? 'verified' : 'not verified' }}</span>
            @if($user->email)
                <span class="badge text-bg-{{ $user->hasVerifiedEmail() ? 'success' : 'secondary' }}">Email {{ $user->hasVerifiedEmail() ? 'verified' : 'not verified' }}</span>
            @endif
        </div>
    </div>
    <div class="btn-group">
        @foreach($user->roles as $r)
            <form method="POST" action="{{ route('role.switch') }}">@csrf<input type="hidden" name="role" value="{{ $r->slug }}">
                <button class="btn btn-sm {{ $r->slug === $role ? 'btn-primary' : 'btn-outline-primary' }}">{{ $r->name }}</button></form>
        @endforeach
    </div>
</div>

@if($user->email && ! $user->hasVerifiedEmail())
    <div class="alert alert-warning d-flex justify-content-between align-items-center">Please verify your email address.
        <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn btn-sm btn-outline-dark">Resend link</button></form></div>
@endif
@unless($user->hasVerifiedMobile())
    <div class="alert alert-info">Add and verify your mobile number in your <a href="{{ route('profile.edit') }}">profile</a> to build trust with other users.</div>
@endunless

<div class="row g-3">
    <div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Your {{ $role }} plan</div>
        <div class="fs-4 fw-semibold">{{ $package?->name ?? 'No plan' }}</div>
        @if($subscription && $subscription->ends_at)
            <div class="small">Valid until {{ $subscription->ends_at->timezone(config('marketplace.timezone'))->format('d M Y') }}</div>
        @elseif($package?->is_free)
            <div class="small text-muted">Free plan, no expiry</div>
        @endif
        <hr>
        <div class="text-muted small">Extra contact credits</div>
        <div class="fs-5">{{ $credits }}</div>
    </div></div></div>
    <div class="col-lg-8"><div class="card shadow-sm h-100"><div class="card-header">What your plan includes</div>
        <table class="table mb-0 align-middle"><thead><tr><th>Feature</th><th>Allowance</th><th>Today</th></tr></thead><tbody>
        @foreach($rows as $row)
            <tr><td>{{ $row['name'] }}</td><td>{{ $row['display'] }}</td>
                <td>@if($row['daily'])
                    {{ $row['used'] }} used @if($row['remaining'] !== null)· <strong>{{ $row['remaining'] }}</strong> left @endif
                @else <span class="text-muted">—</span> @endif</td></tr>
        @endforeach
        </tbody></table>
        <div class="card-footer small text-muted">Daily allowances are shared across all your roles and reset at midnight IST.</div>
    </div></div>
</div>
@endsection
