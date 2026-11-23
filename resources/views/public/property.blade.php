@extends('layouts.app')
@section('title', $seo['title'])
@push('head')@include('partials.seo', ['seo' => $seo, 'crumbs' => $crumbs, 'jsonLd' => $gone ? null : $jsonLd])@endpush
@section('content')
@include('partials.breadcrumbs', ['crumbs' => $crumbs])

@if($gone)
    <div class="alert alert-warning">This property is no longer available. Here are similar listings.</div>
@elseif($property->status !== 'published')
    <div class="alert alert-info">Preview: this listing is <strong>{{ str_replace('_', ' ', $property->status) }}</strong> and is not public yet.</div>
@endif

@if($limitReached)
    <div class="alert alert-warning">You have reached today's property view limit for your plan. Your allowance resets at midnight IST. Viewing properties you have already opened today stays free.</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <h1 class="h4">{{ $property->title }}</h1>
        <div class="text-muted mb-3">{{ $property->typeLabel() }} · {{ $property->locality->name }}, {{ $property->city->name }}@if($property->pin_code) - {{ $property->pin_code }}@endif</div>

        @unless($gone || $limitReached)
            @php $photos = $property->images->where('type', 'photo')->values(); @endphp
            @if($photos->isNotEmpty())
                <img src="{{ $photos[0]->url() }}" alt="{{ $photos[0]->alt }}" class="img-fluid rounded mb-2 w-100" style="max-height:420px;object-fit:cover">
                <div class="d-flex gap-2 flex-wrap mb-3">@foreach($photos->skip(1) as $img)<a href="{{ $img->url() }}" target="_blank" rel="noopener"><img src="{{ $img->thumbUrl() }}" alt="{{ $img->alt }}" class="rounded" style="height:72px;width:96px;object-fit:cover" loading="lazy"></a>@endforeach</div>
            @endif
            @if($property->video)<video controls preload="none" class="w-100 rounded mb-3" src="{{ $property->video->url() }}"></video>@endif

            <div class="card shadow-sm mb-3"><div class="card-body">
                <h2 class="h6">Details</h2>
                <dl class="row small mb-0">
                    @foreach([
                        'Area' => $property->areaSqft() ? number_format($property->areaSqft()).' sq ft' : null,
                        'Bedrooms' => $property->bedrooms, 'Bathrooms' => $property->bathrooms, 'Balconies' => $property->balconies,
                        'Furnishing' => config('property.furnishing')[$property->furnishing] ?? null,
                        'Floor' => $property->floor_number !== null ? $property->floor_number.($property->total_floors ? ' of '.$property->total_floors : '') : null,
                        'Age' => $property->property_age !== null ? ($property->property_age === 0 ? 'New' : $property->property_age.' years') : null,
                        'Facing' => config('property.facing')[$property->facing] ?? null,
                        'Parking' => config('property.parking')[$property->parking] ?? null,
                        'Power backup' => config('property.power_backup')[$property->power_backup] ?? null,
                        'Water supply' => $property->water_supply,
                        'Ownership' => config('property.ownership')[$property->ownership_type] ?? null,
                        'Availability' => config('property.availability')[$property->availability] ?? null,
                        'Preferred tenants' => config('property.tenant_preference')[$property->tenant_preference] ?? null,
                        'Posted by' => ucfirst($property->posted_by),
                    ] as $label => $value)
                        @if($value !== null && $value !== '')<dt class="col-5 col-md-3">{{ $label }}</dt><dd class="col-7 col-md-3">{{ $value }}</dd>@endif
                    @endforeach
                </dl>
            </div></div>

            <div class="card shadow-sm mb-3"><div class="card-body"><h2 class="h6">Description</h2><p class="mb-0" style="white-space:pre-line">{{ $property->description }}</p></div></div>

            @if($property->amenities->isNotEmpty())
                <div class="card shadow-sm mb-3"><div class="card-body"><h2 class="h6">Amenities</h2>@foreach($property->amenities as $a)<span class="badge text-bg-light border me-1">{{ $a->name }}</span>@endforeach</div></div>
            @endif
        @endunless
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="fs-3 fw-semibold">{{ $property->displayPrice() }}</div>
            @if($property->purpose === 'sell' && $property->price_per_sqft)<div class="small text-muted">₹{{ number_format($property->price_per_sqft) }} per sq ft</div>@endif
            @if($property->security_deposit)<div class="small">Security deposit: ₹{{ number_format($property->security_deposit) }}</div>@endif
            @if($property->maintenance_charges)<div class="small">Maintenance: ₹{{ number_format($property->maintenance_charges) }}/month</div>@endif
            @if($property->price_negotiable)<div class="small text-success">Price negotiable</div>@endif

            @unless($gone || $limitReached)
                <hr>
                <div class="small text-muted mb-2">Listed by {{ $property->user->name }} ({{ $property->posted_by }})</div>
                @if($contact)
                    <div class="alert alert-success mb-0"><div class="small">{{ $contact['masked'] ? 'Masked number' : 'Phone number' }}</div><div class="fs-5 fw-semibold">+91 {{ $contact['mobile'] }}</div></div>
                @else
                    @auth
                        <form method="POST" action="{{ route('property.contact', $property) }}">@csrf<button class="btn btn-primary w-100">View contact number</button></form>
                        <div class="form-text">What you can see depends on your plan. Your own contact details are shared only under the same rules.</div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary w-100">Log in to view contact</a>
                    @endauth
                @endif

                @auth
                    @unless($isOwner)
                        <hr>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @if($enquiry?->interest_at)
                                <span class="badge text-bg-success align-self-center">Interest sent</span>
                            @else
                                <form method="POST" action="{{ route('property.interest', $property) }}">@csrf<button class="btn btn-sm btn-outline-primary">Show interest</button></form>
                            @endif
                            @if($isSaved)
                                <form method="POST" action="{{ route('property.unsave', $property) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary">Saved &#10003; Remove</button></form>
                            @else
                                <form method="POST" action="{{ route('property.save', $property) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Save</button></form>
                            @endif
                        </div>
                        @if($enquiry?->enquired_at)
                            <div class="small text-muted">Enquiry sent. <a href="{{ route('enquiries.index') }}">Track it</a></div>
                        @else
                            <form method="POST" action="{{ route('property.enquire', $property) }}">@csrf
                                <textarea name="message" rows="3" class="form-control form-control-sm mb-2" minlength="10" maxlength="1000" placeholder="Ask the poster a question or ask to visit" required></textarea>
                                <button class="btn btn-sm btn-primary w-100">Send enquiry</button>
                            </form>
                        @endif
                    @endunless
                @endauth
            @endunless
        </div></div>
    </div>
</div>

@if($similar->isNotEmpty())
    <h2 class="h5 mt-4 mb-3">Similar properties</h2>
    <div class="row g-3">@foreach($similar as $p)<div class="col-sm-6 col-lg-3">@include('partials.property-card', ['p' => $p])</div>@endforeach</div>
@endif
@endsection
