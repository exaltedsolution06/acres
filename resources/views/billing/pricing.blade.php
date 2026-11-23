@extends('layouts.app')
@section('title', 'Plans and pricing')
@section('content')
@php
    $feat = function ($package, $key) { $v = $package->featureValues->first(fn ($fv) => $fv->feature?->key === $key); return $v; };
    $line = function ($package, $key, $label) use ($feat) {
        $v = $feat($package, $key);
        if (! $v) return null;
        if ($v->feature->type === 'boolean') return $v->value === '1' ? $label : null;
        return $v->is_unlimited ? "Unlimited {$label}" : ((int) $v->value > 0 ? "{$v->value} {$label}" : null);
    };
    $lines = [['daily_property_views', 'property views a day'], ['daily_contact_views', 'contact views a day'], ['active_listings', 'active listings'], ['photo_limit', 'photos per listing'], ['video_upload', 'Video upload'], ['lead_management', 'Lead management'], ['broker_crm', 'Broker CRM'], ['featured_listing', 'Featured listings']];
@endphp
<h1 class="h4 mb-1">Plans and pricing</h1>
<p class="text-muted">Prices exclude 18% GST unless stated otherwise at checkout.</p>

@if($viewingAll)<div class="alert alert-info small">Log in to see the plans for your roles. <a href="{{ route('login') }}">Log in</a> or <a href="{{ route('register') }}">register</a>.</div>@endif

@foreach($roles as $role)
    <h2 class="h5 mt-4 mb-3">{{ $role->name }} plans @if($current[$role->id] ?? null)<span class="badge text-bg-success">Current: {{ $current[$role->id]->package->name }} until {{ $current[$role->id]->ends_at?->timezone(config('marketplace.timezone'))->format('d M Y') }}</span>@endif</h2>
    <div class="row g-3">
    @forelse($packages[$role->id] ?? [] as $pkg)
        <div class="col-md-6 col-lg-4"><div class="card h-100 shadow-sm {{ $pkg->is_recommended ? 'border-warning' : '' }}">
            <div class="card-body">
                @if($pkg->is_recommended)<span class="badge text-bg-warning mb-2">Recommended</span>@endif
                <h3 class="h5">{{ $pkg->name }}</h3>
                <div class="fs-3 fw-semibold">₹{{ number_format($pkg->effectivePrice()) }}@if($pkg->discounted_price)<small class="text-muted text-decoration-line-through fs-6">₹{{ number_format($pkg->price) }}</small>@endif</div>
                <div class="small text-muted mb-2">for {{ $pkg->validity_days }} days + GST</div>
                @if($pkg->description)<p class="small">{{ $pkg->description }}</p>@endif
                <ul class="small ps-3 mb-0">@foreach($lines as [$key, $label])@php $text = $line($pkg, $key, $label); @endphp @if($text)<li>{{ $text }}</li>@endif @endforeach</ul>
            </div>
            <div class="card-footer bg-white border-0 d-flex gap-2">
                @auth
                    <a class="btn btn-primary btn-sm" href="{{ route('checkout.show', ['subscription', $pkg->id]) }}">{{ ($current[$role->id] ?? null)?->package_id === $pkg->id ? 'Renew' : 'Buy' }}</a>
                    @if($pkg->trial_days > 0 && ! in_array($pkg->id, $usedTrials))
                        <form method="POST" action="{{ route('trial', $pkg) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Free {{ $pkg->trial_days }}-day trial</button></form>
                    @endif
                @else
                    <a class="btn btn-primary btn-sm" href="{{ route('login') }}">Log in to buy</a>
                @endauth
            </div>
        </div></div>
    @empty<div class="col-12 text-muted">No paid plans for this role yet.</div>@endforelse
    </div>
@endforeach

@auth
    @if($creditPacks->isNotEmpty())
        <h2 class="h5 mt-5 mb-3">Contact credit packs</h2>
        <p class="small text-muted">Extra phone-number views, used after your daily allowance. They never expire.</p>
        <div class="row g-3">@foreach($creditPacks as $pack)
            <div class="col-sm-6 col-lg-3"><div class="card shadow-sm"><div class="card-body">
                <div class="fw-semibold">{{ $pack->name }}</div><div class="fs-4">₹{{ number_format($pack->price) }}</div><div class="small text-muted mb-2">+ GST</div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('checkout.show', ['credit_pack', $pack->id]) }}">Buy</a></div></div></div>
        @endforeach</div>
    @endif
@endauth
@endsection
