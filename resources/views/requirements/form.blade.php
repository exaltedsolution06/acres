@extends('layouts.app')
@section('title', 'Post a requirement')
@section('content')
<div style="max-width:720px">
<h1 class="h4 mb-3">Post a requirement</h1>
<form method="POST" action="{{ route('requirements.store') }}" class="card card-body shadow-sm row g-3 mx-0">@csrf
    <div class="col-md-6"><label class="form-label">Posting as</label>
        <select name="role" id="role" class="form-select">@foreach($roles as $r)<option value="{{ $r }}" @selected(old('role') === $r)>{{ ucfirst($r) }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Looking to</label>
        <select name="purpose" id="purpose" class="form-select"><option value="sell">Buy</option><option value="rent">Rent</option><option value="lease">Lease</option><option value="pg">PG / Co-living</option></select></div>
    <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title') }}" placeholder="2 BHK apartment in Salt Lake within ₹15,000 rent" required></div>
    <div class="col-md-6"><label class="form-label">City</label><select name="city_id" id="city" class="form-select">@foreach($cities as $c)<option value="{{ $c->id }}" @selected(old('city_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Locality (optional)</label><select name="locality_id" id="locality" class="form-select"><option value="">Anywhere in the city</option>@foreach($localities as $l)<option value="{{ $l->id }}" data-city="{{ $l->parent_id }}" @selected(old('locality_id') == $l->id)>{{ $l->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Property type (optional)</label><select name="category_id" class="form-select"><option value="">Any</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">BHK</label><input type="number" min="1" max="10" name="bhk" class="form-control" value="{{ old('bhk') }}"></div>
    <div class="col-md-3"><label class="form-label">Furnishing</label><select name="furnishing" class="form-select"><option value="">Any</option>@foreach(config('property.furnishing') as $k => $label)<option value="{{ $k }}" @selected(old('furnishing') === $k)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Minimum budget (₹)</label><input type="number" min="0" name="budget_min" class="form-control" value="{{ old('budget_min') }}"></div>
    <div class="col-md-6"><label class="form-label">Maximum budget (₹)</label><input type="number" min="1" name="budget_max" class="form-control" value="{{ old('budget_max') }}"></div>
    <div class="col-12"><label class="form-label">Details (optional)</label><textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea></div>
    <div class="col-12"><button class="btn btn-primary">Post requirement</button> <a href="{{ route('requirements.index') }}" class="btn btn-link">Cancel</a></div>
</form>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var role = document.getElementById('role'), purpose = document.getElementById('purpose'), city = document.getElementById('city'), loc = document.getElementById('locality');
    function syncPurpose() { Array.prototype.forEach.call(purpose.options, function (o) { var buyer = role.value === 'buyer'; o.hidden = buyer ? o.value !== 'sell' : o.value === 'sell'; }); if (purpose.selectedOptions[0].hidden) { purpose.value = Array.prototype.find.call(purpose.options, function (o) { return !o.hidden; }).value; } }
    function syncLoc() { Array.prototype.forEach.call(loc.options, function (o) { o.hidden = !!o.value && o.dataset.city !== city.value; }); if (loc.selectedOptions[0] && loc.selectedOptions[0].hidden) loc.value = ''; }
    role.addEventListener('change', syncPurpose); city.addEventListener('change', syncLoc); syncPurpose(); syncLoc();
})();
</script>
@endpush
