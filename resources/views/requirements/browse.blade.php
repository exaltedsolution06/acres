@extends('layouts.app')
@section('title', 'Browse requirements')
@section('content')
<h1 class="h4 mb-3">Requirements from tenants and buyers</h1>
<form class="row g-2 mb-3" method="GET">
    <div class="col-auto"><select name="purpose" class="form-select form-select-sm"><option value="">Any purpose</option>@foreach(['sell' => 'Buy', 'rent' => 'Rent', 'lease' => 'Lease', 'pg' => 'PG'] as $k => $label)<option value="{{ $k }}" @selected(request('purpose') === $k)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="city_id" class="form-select form-select-sm"><option value="">Any city</option>@foreach($cities as $c)<option value="{{ $c->id }}" @selected(request('city_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
</form>
<div class="vstack gap-3">
@forelse($requirements as $r)
    <div class="card shadow-sm"><div class="card-body d-flex justify-content-between flex-wrap gap-2">
        <div><a class="fw-semibold" href="{{ route('requirements.show', $r) }}">{{ $r->title }}</a>
            <div class="small text-muted">{{ $r->purpose === 'sell' ? 'Buy' : ucfirst($r->purpose) }} · {{ $r->locality?->name ? $r->locality->name.', ' : '' }}{{ $r->city->name }} · {{ $r->budgetLabel() }}@if($r->bhk) · {{ $r->bhk }} BHK @endif</div></div>
        <a href="{{ route('requirements.show', $r) }}" class="btn btn-sm btn-outline-primary align-self-center">View and respond</a>
    </div></div>
@empty<div class="alert alert-light border">No open requirements match.</div>@endforelse
</div>
<div class="mt-3">{{ $requirements->links('pagination::bootstrap-5') }}</div>
@endsection
