@extends('layouts.app')
@section('title', $seo['title'])
@push('head')@include('partials.seo', ['seo' => $seo, 'crumbs' => $crumbs])@endpush
@section('content')
@include('partials.breadcrumbs', ['crumbs' => $crumbs])
<div class="row g-4">
    <aside class="col-lg-3">
        <form method="GET" action="{{ $formAction }}" class="card card-body shadow-sm small">
            @if($formAction === route('search'))
                <label class="form-label mb-1">Purpose</label>
                <select name="purpose" class="form-select form-select-sm mb-2"><option value="">Any</option>@foreach(config('property.purposes') as $k => $label)<option value="{{ $k }}" @selected(($filters['purpose'] ?? '') === $k)>{{ $label }}</option>@endforeach</select>
                <label class="form-label mb-1">City</label>
                <select name="city_id" id="f-city" class="form-select form-select-sm mb-2"><option value="">Any</option>@foreach($cities as $c)<option value="{{ $c->id }}" @selected(($filters['city_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select>
                <label class="form-label mb-1">Locality</label>
                <select name="locality_id" id="f-locality" class="form-select form-select-sm mb-2"><option value="">Any</option>@foreach($localities as $l)<option value="{{ $l->id }}" data-city="{{ $l->parent_id }}" @selected(($filters['locality_id'] ?? '') == $l->id)>{{ $l->name }}</option>@endforeach</select>
            @endif
            <label class="form-label mb-1">Keyword / PIN code</label>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm mb-2">
            @if(! $category)
                <label class="form-label mb-1">Property type</label>
                <div class="mb-2" style="max-height:140px;overflow:auto">@foreach($categories as $c)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $c->id }}" id="c{{ $c->id }}" @checked(in_array($c->id, (array) ($filters['category_ids'] ?? [])))><label class="form-check-label" for="c{{ $c->id }}">{{ $c->name }}</label></div>@endforeach</div>
            @endif
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label mb-1">Min price (₹)</label><input type="number" name="price_min" value="{{ $filters['price_min'] ?? '' }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label mb-1">Max price (₹)</label><input type="number" name="price_max" value="{{ $filters['price_max'] ?? '' }}" class="form-control form-control-sm"></div>
            </div>
            <label class="form-label mb-1">BHK</label>
            <div class="mb-2">@foreach([1, 2, 3, 4, 5] as $b)<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="bhk[]" value="{{ $b }}" id="b{{ $b }}" @checked(in_array($b, array_map('intval', (array) ($filters['bhk'] ?? []))))><label class="form-check-label" for="b{{ $b }}">{{ $b === 5 ? '5+' : $b }}</label></div>@endforeach</div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label mb-1">Min area (sq ft)</label><input type="number" name="area_min" value="{{ $filters['area_min'] ?? '' }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label mb-1">Max area</label><input type="number" name="area_max" value="{{ $filters['area_max'] ?? '' }}" class="form-control form-control-sm"></div>
            </div>
            <label class="form-label mb-1">Furnishing</label>
            <div class="mb-2">@foreach(config('property.furnishing') as $k => $label)<div class="form-check"><input class="form-check-input" type="checkbox" name="furnishing[]" value="{{ $k }}" id="fu{{ $k }}" @checked(in_array($k, (array) ($filters['furnishing'] ?? [])))><label class="form-check-label" for="fu{{ $k }}">{{ $label }}</label></div>@endforeach</div>
            <label class="form-label mb-1">Status</label>
            <select name="availability" class="form-select form-select-sm mb-2"><option value="">Any</option>@foreach(config('property.availability') as $k => $label)<option value="{{ $k }}" @selected(($filters['availability'] ?? '') === $k)>{{ $label }}</option>@endforeach</select>
            <label class="form-label mb-1">Posted by</label>
            <select name="posted_by" class="form-select form-select-sm mb-2"><option value="">Anyone</option><option value="owner" @selected(($filters['posted_by'] ?? '') === 'owner')>Owner</option><option value="broker" @selected(($filters['posted_by'] ?? '') === 'broker')>Broker</option></select>
            <label class="form-label mb-1">Maximum age (years)</label>
            <input type="number" min="0" name="age_max" value="{{ $filters['age_max'] ?? '' }}" class="form-control form-control-sm mb-2">
            <label class="form-label mb-1">Amenities</label>
            <div class="mb-2" style="max-height:120px;overflow:auto">@foreach($amenities as $a)<div class="form-check"><input class="form-check-input" type="checkbox" name="amenities[]" value="{{ $a->id }}" id="a{{ $a->id }}" @checked(in_array($a->id, array_map('intval', (array) ($filters['amenities'] ?? []))))><label class="form-check-label" for="a{{ $a->id }}">{{ $a->name }}</label></div>@endforeach</div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="verified" value="1" id="verified" @checked(! empty($filters['verified']))><label class="form-check-label" for="verified">Verified listings only</label></div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <button class="btn btn-primary btn-sm">Apply filters</button>
            <a class="btn btn-link btn-sm" href="{{ $formAction }}">Clear</a>
        </form>
    </aside>
    <section class="col-lg-9">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h1 class="h5 mb-0">{{ $location ? ($category ? $category->name : 'Properties').' in '.$location->name : 'Search results' }} <span class="text-muted fs-6">({{ $results->total() }})</span></h1>
            <form method="GET" action="{{ $formAction }}" class="d-flex align-items-center gap-2">
                @foreach(request()->except(['sort', 'page']) as $k => $v)@if(is_array($v))@foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach @else<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">@foreach(config('property.sort') as $k => $label)<option value="{{ $k }}" @selected($sort === $k)>{{ $label }}</option>@endforeach</select>
            </form>
        </div>
        @auth
            <form method="POST" action="{{ route('saved.searches.store') }}" class="d-flex flex-wrap gap-2 mb-3">@csrf
                <input type="hidden" name="filters" value="{{ json_encode($filters) }}">
                <input name="name" class="form-control form-control-sm w-auto" placeholder="Name this search" maxlength="100" required>
                <button class="btn btn-sm btn-outline-secondary">Save search and get alerts</button>
            </form>
        @endauth
        @if($results->isEmpty())
            <div class="alert alert-light border">No properties match these filters yet.</div>
        @else
            <div class="row g-3">@foreach($results as $p)<div class="col-sm-6 col-xl-4">@include('partials.property-card', ['p' => $p])</div>@endforeach</div>
            <div class="mt-4">{{ $results->links('pagination::bootstrap-5') }}</div>
        @endif
        @if($recent->isNotEmpty())
            <h2 class="h6 mt-5 mb-3">Recently viewed</h2>
            <div class="row g-3">@foreach($recent as $p)<div class="col-sm-6 col-xl-4">@include('partials.property-card', ['p' => $p])</div>@endforeach</div>
        @endif
    </section>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var city = document.getElementById('f-city'), loc = document.getElementById('f-locality');
    if (!city || !loc) return;
    function sync() { Array.prototype.forEach.call(loc.options, function (o) { o.hidden = o.value && city.value && o.dataset.city !== city.value; }); }
    city.addEventListener('change', function () { loc.value = ''; sync(); }); sync();
})();
</script>
@endpush
