@extends('layouts.app')
@section('title', $seo['title'])
@push('head')@include('partials.seo', ['seo' => $seo, 'crumbs' => null])@endpush
@section('content')
<div class="text-center py-4">
    <h1 class="display-6 fw-bold">Find, list and manage property</h1>
    <p class="lead text-muted">One account for owners, tenants, buyers and brokers.</p>
    <form action="{{ route('search') }}" method="GET" class="row g-2 justify-content-center mt-3">
        <div class="col-6 col-md-2"><select name="purpose" class="form-select">@foreach(config('property.purposes') as $k => $label)<option value="{{ $k }}">{{ $k === 'sell' ? 'Buy' : $label }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><select name="city_id" class="form-select"><option value="">Any city</option>@foreach($cities as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-12 col-md-4"><input name="q" class="form-control" placeholder="Locality, PIN code or keyword"></div>
        <div class="col-12 col-md-auto"><button class="btn btn-primary w-100">Search</button></div>
    </form>
</div>
@if($featured->isNotEmpty())
    <h2 class="h5 mb-3">Featured</h2>
    <div class="row g-3 mb-4">@foreach($featured as $p)<div class="col-sm-6 col-lg-3">@include('partials.property-card', ['p' => $p])</div>@endforeach</div>
@endif
@if($latest->isNotEmpty())
    <h2 class="h5 mb-3">Latest listings</h2>
    <div class="row g-3">@foreach($latest as $p)<div class="col-sm-6 col-lg-3">@include('partials.property-card', ['p' => $p])</div>@endforeach</div>
@else
    <p class="text-center text-muted">No listings yet. @auth <a href="{{ route('listings.create') }}">Post the first one.</a>@endauth</p>
@endif
@endsection
