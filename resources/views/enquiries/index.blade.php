@extends('layouts.app')
@section('title', 'My enquiries')
@section('content')
<h1 class="h4 mb-3">My enquiries</h1>
<div class="vstack gap-3">
@forelse($enquiries as $e)
    <div class="card shadow-sm"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
                <a class="fw-semibold" href="{{ route('property.show', $e->property->slug) }}">{{ $e->property->title }}</a>
                <div class="small text-muted">{{ $e->property->locality->name }}, {{ $e->property->city->name }} · {{ $e->property->displayPrice() }} · poster: {{ $e->poster->name }}</div>
                <div class="small mt-1">
                    @if($e->interest_at)<span class="badge text-bg-light border">Interest shown {{ $e->interest_at->diffForHumans() }}</span>@endif
                    @if($e->enquired_at)<span class="badge text-bg-light border">Enquiry sent {{ $e->enquired_at->diffForHumans() }}</span>@endif
                </div>
                @if($e->message)<p class="small mt-2 mb-0 text-muted" style="white-space:pre-line">{{ $e->message }}</p>@endif
            </div>
            <div class="text-end">
                <span class="badge text-bg-{{ $e->seekerStatus() === 'Closed' ? 'secondary' : ($e->seekerStatus() === 'Sent' ? 'info' : 'success') }}">{{ $e->seekerStatus() }}</span>
                @unless($e->isClosed())
                    <form method="POST" action="{{ route('enquiries.close', $e) }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-secondary">Close</button></form>
                @endunless
            </div>
        </div>

        @foreach($e->visits as $v)
            <div class="small border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                <span>Visit {{ $v->scheduled_at->timezone(config('marketplace.timezone'))->format('d M Y, h:i A') }} · <strong>{{ $v->status }}</strong></span>
                @if(in_array($v->status, ['requested', 'confirmed']))
                    <form method="POST" action="{{ route('visits.action', [$v, 'cancel']) }}">@csrf<button class="btn btn-sm btn-link text-danger p-0">Cancel</button></form>
                @endif
            </div>
        @endforeach

        @unless($e->isClosed())
            <form method="POST" action="{{ route('enquiries.visit', $e) }}" class="row g-2 mt-2 border-top pt-2">@csrf
                <div class="col-md-4"><input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required></div>
                <div class="col-md-5"><input name="note" class="form-control form-control-sm" placeholder="Note (optional)" maxlength="300"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Request a visit</button></div>
            </form>
        @endunless
    </div></div>
@empty
    <div class="alert alert-light border">You have not contacted anyone yet. Open a property and use <em>Show interest</em> or <em>Send enquiry</em>.</div>
@endforelse
</div>
<div class="mt-3">{{ $enquiries->links('pagination::bootstrap-5') }}</div>
@endsection
