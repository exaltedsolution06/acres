@extends('layouts.app')
@section('title', 'Saved properties')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Saved properties</h1><a href="{{ route('saved.searches') }}" class="btn btn-sm btn-outline-secondary">Saved searches</a></div>
<div class="row g-3">
@forelse($saved as $s)
    @if($s->property)
        <div class="col-sm-6 col-lg-4">
            @include('partials.property-card', ['p' => $s->property])
            <form method="POST" action="{{ route('property.unsave', $s->property) }}" class="mt-1">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">Remove</button></form>
            @unless($s->property->isVisible())<div class="small text-warning">No longer available</div>@endunless
        </div>
    @endif
@empty<div class="col-12"><div class="alert alert-light border">Nothing saved yet.</div></div>@endforelse
</div>
<div class="mt-3">{{ $saved->links('pagination::bootstrap-5') }}</div>
@endsection
