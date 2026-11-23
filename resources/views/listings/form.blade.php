@extends('layouts.app')
@section('title', $property->exists ? 'Edit listing' : 'Post a property')
@section('content')
@php
    $v = fn ($field, $default = null) => old($field, $property->$field ?? $default);
    $opt = fn ($list, $field) => collect(config("property.{$list}"))->map(fn ($label, $k) => '<option value="'.e($k).'"'.($v($field) === $k ? ' selected' : '').'>'.e($label).'</option>')->implode('');
@endphp

@if($postingRoles->isEmpty())
    <div class="card card-body shadow-sm" style="max-width:560px">
        <h1 class="h5">Add a posting role first</h1>
        <p>Posting properties needs the Owner or Broker role on your account.</p>
        <form method="POST" action="{{ route('role.add') }}" class="d-flex gap-2">@csrf
            <button name="role" value="owner" class="btn btn-outline-primary">I am an owner</button>
            <button name="role" value="broker" class="btn btn-outline-primary">I am a broker</button>
        </form>
    </div>
@else
<h1 class="h4 mb-3">{{ $property->exists ? 'Edit listing' : 'Post a property' }}
    @if($property->exists)<span class="badge text-bg-secondary fs-6 align-middle">{{ str_replace('_', ' ', $property->status) }}</span>@endif</h1>

@if($property->exists && $property->status === 'rejected' && $property->rejection_reason)
    <div class="alert alert-danger"><strong>Rejected:</strong> {{ $property->rejection_reason }} Fix the issue, save, and submit again.</div>
@endif

<form method="POST" action="{{ $property->exists ? route('listings.update', $property) : route('listings.store') }}" class="row g-4">
    @csrf @if($property->exists) @method('PUT') @endif
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3"><div class="card-header">Basics</div><div class="card-body row g-3">
            @if($postingRoles->count() > 1)
                <div class="col-md-4"><label class="form-label">Posting as</label><select name="posted_by" class="form-select">@foreach($postingRoles as $r)<option value="{{ $r }}" @selected($v('posted_by') === $r)>{{ ucfirst($r) }}</option>@endforeach</select></div>
            @else<input type="hidden" name="posted_by" value="{{ $postingRoles->first() }}">@endif
            <div class="col-md-4"><label class="form-label">Purpose</label><select name="purpose" id="purpose" class="form-select">{!! $opt('purposes', 'purpose') !!}</select></div>
            <div class="col-md-4"><label class="form-label">Category</label><select name="category_id" class="form-select">@foreach($categories as $c)<option value="{{ $c->id }}" @selected($v('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ $v('title') }}" placeholder="2 BHK apartment near New Town metro" required></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="5" class="form-control" required>{{ $v('description') }}</textarea></div>
            <div class="col-md-4"><label class="form-label">Availability</label><select name="availability" class="form-select">{!! $opt('availability', 'availability') !!}</select></div>
            <div class="col-md-4"><label class="form-label">Ownership</label><select name="ownership_type" class="form-select"><option value="">—</option>{!! $opt('ownership', 'ownership_type') !!}</select></div>
            <div class="col-md-4"><label class="form-label">Available from</label><input type="date" name="available_from" class="form-control" value="{{ $property->available_from?->format('Y-m-d') ?? old('available_from') }}"></div>
        </div></div>

        <div class="card shadow-sm mb-3"><div class="card-header">Pricing</div><div class="card-body row g-3">
            <div class="col-md-4" id="price-box"><label class="form-label">Expected price (₹)</label><input type="number" min="1" name="price" class="form-control" value="{{ $v('price') }}"></div>
            <div class="col-md-4" id="rent-box"><label class="form-label">Monthly rent (₹)</label><input type="number" min="1" name="monthly_rent" class="form-control" value="{{ $v('monthly_rent') }}"></div>
            <div class="col-md-4"><label class="form-label">Security deposit (₹)</label><input type="number" min="0" name="security_deposit" class="form-control" value="{{ $v('security_deposit') }}"></div>
            <div class="col-md-4"><label class="form-label">Maintenance (₹/month)</label><input type="number" min="0" name="maintenance_charges" class="form-control" value="{{ $v('maintenance_charges') }}"></div>
            <div class="col-md-4"><label class="form-label">Preferred tenants</label><select name="tenant_preference" class="form-select"><option value="">—</option>{!! $opt('tenant_preference', 'tenant_preference') !!}</select></div>
            <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="price_negotiable" value="1" id="neg" @checked($v('price_negotiable'))><label class="form-check-label" for="neg">Price negotiable</label></div></div>
        </div></div>

        <div class="card shadow-sm mb-3"><div class="card-header">Specifications</div><div class="card-body row g-3">
            @foreach(['bhk' => 'BHK', 'bedrooms' => 'Bedrooms', 'bathrooms' => 'Bathrooms', 'balconies' => 'Balconies', 'carpet_area' => 'Carpet area (sq ft)', 'built_up_area' => 'Built-up area (sq ft)', 'super_built_up_area' => 'Super built-up (sq ft)', 'floor_number' => 'Floor number', 'total_floors' => 'Total floors', 'property_age' => 'Age (years, 0 = new)'] as $field => $label)
                <div class="col-6 col-md-3"><label class="form-label">{{ $label }}</label><input type="number" name="{{ $field }}" class="form-control" value="{{ $v($field) }}"></div>
            @endforeach
            <div class="col-6 col-md-3"><label class="form-label">Furnishing</label><select name="furnishing" class="form-select"><option value="">—</option>{!! $opt('furnishing', 'furnishing') !!}</select></div>
            <div class="col-6 col-md-3"><label class="form-label">Facing</label><select name="facing" class="form-select"><option value="">—</option>{!! $opt('facing', 'facing') !!}</select></div>
            <div class="col-6 col-md-3"><label class="form-label">Parking</label><select name="parking" class="form-select"><option value="">—</option>{!! $opt('parking', 'parking') !!}</select></div>
            <div class="col-6 col-md-3"><label class="form-label">Power backup</label><select name="power_backup" class="form-select"><option value="">—</option>{!! $opt('power_backup', 'power_backup') !!}</select></div>
            <div class="col-12 col-md-6"><label class="form-label">Water supply</label><input name="water_supply" class="form-control" value="{{ $v('water_supply') }}" placeholder="Municipal, borewell..."></div>
        </div></div>

        <div class="card shadow-sm mb-3"><div class="card-header">Location</div><div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">City</label><select name="city_id" id="city" class="form-select">@foreach($cities as $c)<option value="{{ $c->id }}" @selected($v('city_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Locality</label><select name="locality_id" id="locality" class="form-select">@foreach($localities as $l)<option value="{{ $l->id }}" data-city="{{ $l->parent_id }}" @selected($v('locality_id') == $l->id)>{{ $l->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">PIN code</label><input name="pin_code" class="form-control" maxlength="6" value="{{ $v('pin_code') }}"></div>
            <div class="col-md-6"><label class="form-label">Sub-locality / landmark</label><input name="sub_locality" class="form-control" value="{{ $v('sub_locality') }}"></div>
            <div class="col-md-6"><label class="form-label">Full address (kept private)</label><input name="address" class="form-control" value="{{ $v('address') }}"></div>
            <div class="col-md-3"><label class="form-label">Latitude</label><input name="latitude" class="form-control" value="{{ $v('latitude') }}"></div>
            <div class="col-md-3"><label class="form-label">Longitude</label><input name="longitude" class="form-control" value="{{ $v('longitude') }}"></div>
        </div></div>

        <div class="card shadow-sm mb-3"><div class="card-header">Amenities</div><div class="card-body">
            @foreach($amenities as $a)<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" value="{{ $a->id }}" id="am{{ $a->id }}" @checked(in_array($a->id, old('amenities', $selectedAmenities)))><label class="form-check-label" for="am{{ $a->id }}">{{ $a->name }}</label></div>@endforeach
        </div></div>

        @if($canEdit)<button class="btn btn-primary">{{ $property->exists ? 'Save changes' : 'Save draft' }}</button>@endif
        <a class="btn btn-link" href="{{ route('listings.index') }}">Back to my listings</a>
    </div>

    <div class="col-lg-4">
        @if($property->exists)
        <div class="card shadow-sm mb-3"><div class="card-header">Photos</div><div class="card-body">
            <div class="small text-muted mb-2">{{ $property->images->where('type', 'photo')->count() }} of {{ $photoLimit === null ? 'unlimited' : $photoLimit }} photos used. At least {{ config('property.min_photos') }} needed to submit.</div>
            <div class="row g-2 mb-3">
                @foreach($property->images as $img)
                    <div class="col-6"><div class="border rounded p-1 small">
                        <img src="{{ $img->thumbUrl() }}" alt="{{ $img->alt }}" class="img-fluid rounded mb-1">
                        <div class="d-flex justify-content-between align-items-center"><span>{{ $img->type === 'floor_plan' ? 'Floor plan' : ($img->is_cover ? 'Cover' : 'Photo') }}</span>
                        <span class="text-nowrap">@if($img->type === 'photo' && ! $img->is_cover)<button form="cover-{{ $img->id }}" class="btn btn-link btn-sm p-0">Cover</button> @endif<button form="del-{{ $img->id }}" class="btn btn-link btn-sm p-0 text-danger">Remove</button></span></div>
                    </div></div>
                @endforeach
            </div>
        </div></div>
        @endif
    </div>
</form>

@if($property->exists)
    {{-- Forms live outside the main form (no nested forms); buttons above reference them by id. --}}
    @foreach($property->images as $img)
        <form id="cover-{{ $img->id }}" method="POST" action="{{ route('listings.images.cover', [$property, $img]) }}">@csrf</form>
        <form id="del-{{ $img->id }}" method="POST" action="{{ route('listings.images.remove', [$property, $img]) }}">@csrf @method('DELETE')</form>
    @endforeach

    <div class="row g-4 mt-1"><div class="col-lg-8">
        @if($canEdit)
        <div class="card shadow-sm mb-3"><div class="card-header">Upload media</div><div class="card-body">
            <form method="POST" action="{{ route('listings.images.add', $property) }}" enctype="multipart/form-data" class="row g-2 mb-3">@csrf
                <div class="col-md-3"><select name="type" class="form-select form-select-sm"><option value="photo">Photos</option><option value="floor_plan">Floor plan</option></select></div>
                <div class="col-md-6"><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" required></div>
                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Upload</button></div>
                <div class="form-text">JPG, PNG or WebP, up to {{ config('property.photo_max_kb') / 1024 }} MB each. Images are resized and location data is removed.</div>
            </form>
            @if($canVideo)
                <form method="POST" action="{{ route('listings.video.set', $property) }}" enctype="multipart/form-data" class="row g-2">@csrf
                    <div class="col-md-9"><input type="file" name="video" accept="video/mp4,video/webm" class="form-control form-control-sm" required>
                        <div class="form-text">MP4 or WebM up to {{ round(config('property.video_max_kb') / 1024) }} MB. A new upload replaces the current video.</div></div>
                    <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Upload video</button></div>
                </form>
                @if($property->video)<form method="POST" action="{{ route('listings.video.remove', $property) }}" class="mt-2">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove video</button></form>@endif
            @else
                <div class="small text-muted">Video upload is not included in your current plan.</div>
            @endif
        </div></div>
        @endif

        @if(in_array($property->status, ['draft', 'rejected', 'expired']))
            <form method="POST" action="{{ route('listings.submit', $property) }}">@csrf<button class="btn btn-success">Submit for approval</button></form>
        @endif

        <div class="card shadow-sm mt-3"><div class="card-header">History</div>
            <ul class="list-group list-group-flush small">@foreach($property->histories as $h)<li class="list-group-item">{{ $h->created_at->timezone(config('marketplace.timezone'))->format('d M Y H:i') }} · {{ str_replace('_', ' ', $h->to_status) }}@if($h->note) — {{ $h->note }}@endif</li>@endforeach</ul></div>
    </div></div>
@endif
@endif
@endsection
@push('scripts')
<script>
(function () {
    var purpose = document.getElementById('purpose'), price = document.getElementById('price-box'), rent = document.getElementById('rent-box');
    function togglePrice() { if (!purpose) return; var sell = purpose.value === 'sell'; price.style.display = sell ? '' : 'none'; rent.style.display = sell ? 'none' : ''; }
    if (purpose) { purpose.addEventListener('change', togglePrice); togglePrice(); }
    var city = document.getElementById('city'), loc = document.getElementById('locality');
    function syncLoc() { if (!city || !loc) return; Array.prototype.forEach.call(loc.options, function (o) { o.hidden = o.dataset.city !== city.value; }); if (loc.selectedOptions[0] && loc.selectedOptions[0].hidden) { var first = Array.prototype.find.call(loc.options, function (o) { return !o.hidden; }); if (first) loc.value = first.value; } }
    if (city) { city.addEventListener('change', syncLoc); syncLoc(); }
})();
</script>
@endpush
