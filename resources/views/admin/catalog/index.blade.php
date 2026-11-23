@extends('layouts.admin')
@section('title', ucfirst($type))
@section('content')
<h1 class="h4 mb-3">{{ ucfirst($type) }}</h1>
<div class="alert alert-info small">Items are deactivated rather than deleted, because listings reference them.
@if($type !== 'amenities') Names are fixed after creation to keep public URLs stable.@endif</div>

<div class="card shadow-sm mb-4"><div class="card-header">Add</div><div class="card-body">
    <form method="POST" action="{{ route('admin.catalog.store', $type) }}" class="row g-2">@csrf
        @if($type === 'categories')
            <div class="col-md-3"><select name="group" class="form-select form-select-sm"><option value="residential">Residential</option><option value="commercial">Commercial</option></select></div>
            <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="Category name" required></div>
            <div class="col-md-3 align-self-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="has_bhk" value="1" id="nb"><label class="form-check-label small" for="nb">Uses BHK</label></div></div>
        @elseif($type === 'amenities')
            <div class="col-md-6"><input name="name" class="form-control form-control-sm" placeholder="Amenity name" required></div>
        @else
            <div class="col-md-2"><select name="location_type" id="lt" class="form-select form-select-sm"><option value="state">State</option><option value="city">City</option><option value="locality" selected>Locality</option></select></div>
            <div class="col-md-3"><select name="parent_id" class="form-select form-select-sm"><option value="">Parent (state for a city, city for a locality)</option>
                @foreach($states as $s)<option value="{{ $s->id }}">State: {{ $s->name }}</option>@endforeach
                @foreach($cities as $c)<option value="{{ $c->id }}">City: {{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
            <div class="col-md-2"><input name="pin_code" class="form-control form-control-sm" placeholder="PIN" maxlength="6"></div>
        @endif
        <div class="col-md-auto"><button class="btn btn-sm btn-primary">Add</button></div>
    </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Name</th>@if($type === 'categories')<th>Group</th><th>Order</th><th>BHK</th>@endif @if($type === 'locations')<th>Type</th><th>Parent</th><th>PIN</th><th>SEO slug</th>@endif<th>Active</th><th></th></tr></thead><tbody>
    @foreach($items as $item)
        <tr>
            <td>@if($type === 'amenities')<input form="f{{ $item->id }}" name="name" value="{{ $item->name }}" class="form-control form-control-sm">@else{{ $item->name }}@endif</td>
            @if($type === 'categories')<td>{{ $item->group }}</td><td><input form="f{{ $item->id }}" type="number" name="sort_order" value="{{ $item->sort_order }}" class="form-control form-control-sm" style="width:80px"></td>
                <td><input form="f{{ $item->id }}" type="checkbox" class="form-check-input" name="has_bhk" value="1" @checked($item->has_bhk)></td>@endif
            @if($type === 'locations')<td>{{ $item->type }}</td><td>{{ $item->parent?->name }}</td><td><input form="f{{ $item->id }}" name="pin_code" value="{{ $item->pin_code }}" maxlength="6" class="form-control form-control-sm" style="width:90px"></td><td><code>{{ $item->seo_slug }}</code></td>@endif
            <td><input form="f{{ $item->id }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($item->is_active)></td>
            <td class="text-end"><button form="f{{ $item->id }}" class="btn btn-sm btn-outline-primary">Save</button></td>
        </tr>
    @endforeach
    </tbody></table></div></div>
@foreach($items as $item)<form id="f{{ $item->id }}" method="POST" action="{{ route('admin.catalog.update', [$type, $item->id]) }}">@csrf @method('PUT')</form>@endforeach
@endsection
