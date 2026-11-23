@extends('layouts.app')
@section('title', $requirement->title)
@section('content')
<a href="{{ $isOwner ? route('requirements.index') : route('requirements.browse') }}" class="small">&larr; Back</a>
<h1 class="h4 my-2">{{ $requirement->title }}</h1>
<p class="text-muted">{{ $requirement->purpose === 'sell' ? 'Buy' : ucfirst($requirement->purpose) }} · {{ $requirement->locality?->name ? $requirement->locality->name.', ' : '' }}{{ $requirement->city->name }} · {{ $requirement->budgetLabel() }}
    @if($requirement->category) · {{ $requirement->category->name }}@endif @if($requirement->bhk) · {{ $requirement->bhk }} BHK @endif · posted by {{ $requirement->user->name }}</p>
@if($requirement->description)<p style="white-space:pre-line">{{ $requirement->description }}</p>@endif

@if($contact && ! isset($contact['for']))
    <div class="alert alert-success"><div class="small">{{ $contact['masked'] ? 'Masked number' : 'Phone number' }}</div><div class="fs-5 fw-semibold">+91 {{ $contact['mobile'] }}</div></div>
@endif

@if($isOwner)
    <h2 class="h5 mt-4">Responses ({{ $responses->count() }})</h2>
    <div class="vstack gap-2 mb-4">
    @forelse($responses as $resp)
        <div class="card shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between"><strong>{{ $resp->user->name }}</strong><span class="small text-muted">{{ $resp->created_at->diffForHumans() }}</span></div>
            <p class="mb-1" style="white-space:pre-line">{{ $resp->message }}</p>
            @if($resp->property)<a class="small" href="{{ route('property.show', $resp->property->slug) }}">{{ $resp->property->title }}</a>@endif
            <div class="mt-2">
                @if($contact && ($contact['for'] ?? null) === $resp->id)
                    <span class="badge text-bg-success fs-6">+91 {{ $contact['mobile'] }}</span>
                @else
                    <form method="POST" action="{{ route('requirements.responses.contact', [$requirement, $resp]) }}">@csrf<button class="btn btn-sm btn-outline-primary">View contact number</button></form>
                @endif
            </div>
        </div></div>
    @empty<div class="text-muted">No responses yet.</div>@endforelse
    </div>

    <h2 class="h5">Matching listings ({{ $matches->count() }})</h2>
    <div class="row g-3">@forelse($matches as $p)<div class="col-sm-6 col-lg-4">@include('partials.property-card', ['p' => $p])</div>@empty<div class="text-muted col-12">No matching listings yet. You will be notified when one is published.</div>@endforelse</div>
@else
    @if($responded)
        <div class="alert alert-success">You have responded to this requirement.</div>
        <form method="POST" action="{{ route('requirements.contact', $requirement) }}">@csrf<button class="btn btn-primary">View requirement owner's contact</button></form>
    @else
        <div class="card shadow-sm" style="max-width:640px"><div class="card-header">Respond</div><div class="card-body">
            <form method="POST" action="{{ route('requirements.respond', $requirement) }}">@csrf
                <div class="mb-2"><label class="form-label">Offer one of your live listings (optional)</label>
                    <select name="property_id" class="form-select form-select-sm"><option value="">No specific listing</option>@foreach($myProperties as $p)<option value="{{ $p->id }}">{{ $p->title }}</option>@endforeach</select></div>
                <textarea name="message" rows="3" class="form-control mb-2" minlength="10" maxlength="1000" placeholder="Tell them what you can offer" required></textarea>
                <button class="btn btn-primary">Send response</button>
                <div class="form-text">Uses one of your daily enquiries.</div>
            </form>
        </div></div>
    @endif
@endif
@endsection
