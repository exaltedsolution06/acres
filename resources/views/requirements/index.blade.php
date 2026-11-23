@extends('layouts.app')
@section('title', 'My requirements')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">My requirements</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('requirements.browse') }}" class="btn btn-sm btn-outline-secondary">Browse requirements (owners and brokers)</a>
        <a href="{{ route('requirements.create') }}" class="btn btn-sm btn-primary">Post a requirement</a>
    </div>
</div>
<div class="vstack gap-3">
@forelse($requirements as $r)
    <div class="card shadow-sm"><div class="card-body d-flex justify-content-between flex-wrap gap-2">
        <div>
            <a href="{{ route('requirements.show', $r) }}" class="fw-semibold">{{ $r->title }}</a>
            <div class="small text-muted">{{ ucfirst($r->purpose === 'sell' ? 'buy' : $r->purpose) }} · {{ $r->locality?->name ? $r->locality->name.', ' : '' }}{{ $r->city->name }} · {{ $r->budgetLabel() }}</div>
            <div class="small mt-1"><span class="badge text-bg-light border">{{ $r->matches_count }} matching listings</span> <span class="badge text-bg-light border">{{ $r->responses_count }} responses</span></div>
        </div>
        <div class="text-end"><span class="badge text-bg-{{ $r->isOpen() ? 'success' : 'secondary' }}">{{ $r->isOpen() ? 'Active until '.$r->expires_at->format('d M') : ($r->status === 'closed' ? 'Closed' : 'Expired') }}</span>
            @if($r->isOpen())<form method="POST" action="{{ route('requirements.close', $r) }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-secondary">Close</button></form>@endif</div>
    </div></div>
@empty
    <div class="alert alert-light border">Tell owners and brokers what you are looking for and we will match it against new listings.</div>
@endforelse
</div>
<div class="mt-3">{{ $requirements->links('pagination::bootstrap-5') }}</div>
@endsection
