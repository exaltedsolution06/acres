@extends('layouts.app')
@section('title', 'Lead: '.$enquiry->seeker->name)
@section('content')
<a href="{{ route('leads.index') }}" class="small">&larr; Leads</a>
<h1 class="h4 my-2">{{ $enquiry->seeker->name }} <span class="badge text-bg-light border fs-6">{{ config('engagement.statuses')[$enquiry->status] ?? $enquiry->status }}</span></h1>
<p class="text-muted">On <a href="{{ route('property.show', $enquiry->property->slug) }}">{{ $enquiry->property->title }}</a> · {{ $enquiry->property->displayPrice() }}</p>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-3"><div class="card-body">
            @if($enquiry->interest_at)<div class="small text-muted">Showed interest {{ $enquiry->interest_at->timezone(config('marketplace.timezone'))->format('d M Y, h:i A') }}</div>@endif
            @if($enquiry->message)<p class="mt-2 mb-0" style="white-space:pre-line">{{ $enquiry->message }}</p>@endif
            <hr>
            @if($contact)
                <div class="alert alert-success mb-0"><div class="small">{{ $contact['masked'] ? 'Masked number' : 'Phone number' }}</div><div class="fs-5 fw-semibold">+91 {{ $contact['mobile'] }}</div></div>
            @else
                <form method="POST" action="{{ route('leads.contact', $enquiry) }}">@csrf<button class="btn btn-primary">View contact number</button>
                    <span class="form-text ms-2">Uses your plan's contact allowance.</span></form>
            @endif
        </div></div>

        <div class="card shadow-sm mb-3"><div class="card-header">Site visits</div>
            <ul class="list-group list-group-flush">
                @forelse($enquiry->visits as $v)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $v->scheduled_at->timezone(config('marketplace.timezone'))->format('d M Y, h:i A') }} · <strong>{{ $v->status }}</strong>@if($v->note)<span class="text-muted"> — {{ $v->note }}</span>@endif</span>
                        <span class="text-nowrap">
                            @foreach(['requested' => ['confirm' => 'Confirm', 'decline' => 'Decline'], 'confirmed' => ['complete' => 'Done', 'cancel' => 'Cancel']][$v->status] ?? [] as $action => $label)
                                <form class="d-inline" method="POST" action="{{ route('visits.action', [$v, $action]) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $label }}</button></form>
                            @endforeach
                        </span>
                    </li>
                @empty<li class="list-group-item text-muted">No visits yet.</li>@endforelse
            </ul>
            @unless($enquiry->isClosed())
            <div class="card-body border-top"><form method="POST" action="{{ route('leads.visit', $enquiry) }}" class="row g-2">@csrf
                <div class="col-md-5"><input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required></div>
                <div class="col-md-4"><input name="note" class="form-control form-control-sm" placeholder="Note" maxlength="300"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Schedule visit</button></div></form></div>
            @endunless
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-3"><div class="card-header">Pipeline</div><div class="card-body">
            <form method="POST" action="{{ route('leads.status', $enquiry) }}" class="d-flex gap-2">@csrf
                <select name="status" class="form-select form-select-sm">
                    @foreach(config('engagement.statuses') as $key => $label)
                        <option value="{{ $key }}" @selected($enquiry->status === $key) @disabled(! $canManage && ! in_array($key, config('engagement.free_statuses')))>{{ $label }}@if(! $canManage && ! in_array($key, config('engagement.free_statuses'))) (plan upgrade)@endif</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">Update</button></form>
            @unless($canManage)<div class="form-text">Your plan allows New and Closed. Lead management (full pipeline, notes, follow-ups) is a plan feature.</div>@endunless
        </div></div>

        @if($canManage)
        <div class="card shadow-sm mb-3"><div class="card-header">Follow-up reminder</div><div class="card-body">
            <form method="POST" action="{{ route('leads.follow-up', $enquiry) }}" class="row g-2">@csrf
                <div class="col-12"><input type="datetime-local" name="follow_up_at" class="form-control form-control-sm" value="{{ $enquiry->follow_up_at?->timezone(config('marketplace.timezone'))->format('Y-m-d\TH:i') }}"></div>
                <div class="col-12"><input name="follow_up_note" class="form-control form-control-sm" placeholder="What to follow up on" maxlength="300" value="{{ $enquiry->follow_up_note }}"></div>
                <div class="col-12"><button class="btn btn-sm btn-outline-primary">Save (leave empty to clear)</button></div></form>
        </div></div>

        <div class="card shadow-sm"><div class="card-header">Private notes</div>
            <div class="card-body"><form method="POST" action="{{ route('leads.note', $enquiry) }}">@csrf<textarea name="body" rows="2" class="form-control form-control-sm mb-2" required maxlength="2000"></textarea><button class="btn btn-sm btn-outline-primary">Add note</button></form></div>
            <ul class="list-group list-group-flush small">@foreach($enquiry->notes as $n)<li class="list-group-item"><div style="white-space:pre-line">{{ $n->body }}</div><div class="text-muted">{{ $n->created_at->diffForHumans() }}</div></li>@endforeach</ul>
        </div>
        @endif
    </div>
</div>
@endsection
