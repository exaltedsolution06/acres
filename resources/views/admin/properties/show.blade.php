@extends('layouts.admin')
@section('title', $property->title)
@section('content')
<a href="{{ route('admin.properties.index') }}" class="small">&larr; Properties</a>
<h1 class="h4 my-2">{{ $property->title }} <span class="badge text-bg-secondary fs-6">{{ str_replace('_', ' ', $property->status) }}</span></h1>
<p class="text-muted">{{ $property->typeLabel() }} · {{ $property->locality->name }}, {{ $property->city->name }} · posted by {{ $property->user->name }} ({{ $property->posted_by }}) · <code>{{ $property->public_id }}</code>
    @if($property->status === 'published')· <a href="{{ route('property.show', $property->slug) }}" target="_blank">public page</a>@endif</p>

@if($original)<div class="alert alert-warning">Possible duplicate of <a href="{{ route('admin.properties.show', $original) }}">{{ $original->title }}</a> (poster: {{ $original->user->name }}, status: {{ $original->status }}). Same category, area, floor and address.</div>@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="fs-4 fw-semibold">{{ $property->displayPrice() }}</div>
            <p class="mt-2" style="white-space:pre-line">{{ $property->description }}</p>
            <dl class="row small mb-0">
                @foreach(['Address (private)' => $property->address, 'PIN' => $property->pin_code, 'Sub-locality' => $property->sub_locality, 'Area' => $property->areaSqft() ? $property->areaSqft().' sq ft' : null, 'BHK' => $property->bhk, 'Floor' => $property->floor_number, 'Furnishing' => $property->furnishing, 'Ownership' => $property->ownership_type, 'Poster mobile' => $property->user->mobile, 'Poster email' => $property->user->email] as $label => $value)
                    @if($value)<dt class="col-4 col-md-3">{{ $label }}</dt><dd class="col-8 col-md-9">{{ $value }}</dd>@endif
                @endforeach
            </dl>
            @if($property->amenities->isNotEmpty())<div class="mt-2">@foreach($property->amenities as $a)<span class="badge text-bg-light border">{{ $a->name }}</span> @endforeach</div>@endif
        </div></div>
        <div class="row g-2 mb-3">@foreach($property->images as $img)<div class="col-4 col-md-3"><a href="{{ $img->url() }}" target="_blank" rel="noopener"><img src="{{ $img->thumbUrl() }}" alt="{{ $img->alt }}" class="img-fluid rounded border"></a><div class="small text-muted">{{ $img->type === 'floor_plan' ? 'Floor plan' : ($img->is_cover ? 'Cover' : 'Photo') }}</div></div>@endforeach</div>
        @if($property->video)<video controls preload="none" class="w-100 rounded mb-3" src="{{ $property->video->url() }}"></video>@endif
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-header">Decision</div><div class="card-body">
            @if($property->status === 'pending_approval')
                <form method="POST" action="{{ route('admin.properties.approve', $property) }}" class="mb-3">@csrf<button class="btn btn-success w-100">Approve and publish</button>
                    <div class="form-text">Goes live for {{ config('property.listing_validity_days') }} days.</div></form>
            @endif
            @if(in_array($property->status, ['pending_approval', 'published']))
                <form method="POST" action="{{ route('admin.properties.reject', $property) }}">@csrf
                    <label class="form-label small">{{ $property->status === 'published' ? 'Take down with a reason' : 'Reject with a reason' }} (shown to the poster)</label>
                    <textarea name="reason" rows="3" class="form-control mb-2" required minlength="10" maxlength="500"></textarea>
                    <button class="btn btn-outline-danger w-100">{{ $property->status === 'published' ? 'Take down' : 'Reject' }}</button></form>
            @else
                <div class="text-muted small">No moderation action available for this status.</div>
            @endif
        </div></div>
        <div class="card shadow-sm"><div class="card-header">History</div>
            <ul class="list-group list-group-flush small">@foreach($property->histories as $h)<li class="list-group-item">{{ $h->created_at->timezone(config('marketplace.timezone'))->format('d M Y H:i') }} · {{ $h->actor_type }} · {{ str_replace('_', ' ', $h->to_status) }}@if($h->note)<br><span class="text-muted">{{ $h->note }}</span>@endif</li>@endforeach</ul></div>
    </div>
</div>
@endsection
