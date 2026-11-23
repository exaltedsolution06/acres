@extends('layouts.app')
@section('title', 'Promote listing')
@section('content')
<a href="{{ route('listings.index') }}" class="small">&larr; My listings</a>
<h1 class="h4 my-2">Promote "{{ $property->title }}"</h1>
<p class="text-muted">Featured listings appear first in search results with a badge. Purchases stack: a new promotion starts when the current one ends.</p>
@if($property->isFeatured())<div class="alert alert-success">Featured until {{ $property->featured_until->timezone(config('marketplace.timezone'))->format('d M Y, h:i A') }}.</div>@endif
<div class="row g-3">
@forelse($packages as $pkg)
    <div class="col-md-6 col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
        <h2 class="h6">{{ $pkg->name }}</h2><div class="fs-4">₹{{ number_format($pkg->price) }}</div><div class="small text-muted mb-2">+ GST · {{ $pkg->duration_days }} days</div>
        <ul class="small ps-3"><li>Top of search results</li><li>Featured badge</li>@if($pkg->show_on_homepage)<li>Shown on the homepage</li>@endif</ul>
        <a class="btn btn-warning btn-sm" href="{{ route('checkout.show', ['promotion', $pkg->id, 'property' => $property->public_id]) }}">Promote</a>
    </div></div></div>
@empty<div class="col-12 text-muted">No promotion packages are available right now.</div>@endforelse
</div>
@endsection
