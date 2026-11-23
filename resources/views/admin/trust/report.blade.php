@extends('layouts.admin')
@section('title', 'Review report')
@section('content')
<a href="{{ route('admin.reports.index') }}" class="small">&larr; Reports</a>
<h1 class="h4 my-2">{{ $report->reasonLabel() }} <span class="badge text-bg-secondary fs-6">{{ $report->status }}</span></h1>
<p class="text-muted">Listing: <a href="{{ route('admin.properties.show', $report->property) }}">{{ $report->property->title }}</a> ({{ $report->property->status }}) · poster {{ $report->property->user->name }} · reported by {{ $report->reporter?->name }} {{ $report->created_at->diffForHumans() }}</p>
@if($report->details)<div class="card card-body shadow-sm mb-3" style="white-space:pre-line">{{ $report->details }}</div>@endif
<div class="row g-3">
    <div class="col-lg-6">
        @if($report->status === 'open')
            <div class="card shadow-sm mb-3"><div class="card-header">Take action</div><div class="card-body">
                <form method="POST" action="{{ route('admin.reports.action', $report) }}">@csrf
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="take_down" value="1" id="td"><label class="form-check-label" for="td">Take the listing down (the poster can fix and resubmit)</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="suspend_poster" value="1" id="sp"><label class="form-check-label" for="sp">Suspend the poster's account</label></div>
                    <textarea name="note" rows="2" class="form-control mb-2" maxlength="500" placeholder="Internal note"></textarea>
                    <button class="btn btn-danger">Record action</button>
                    <div class="form-text">All open reports on this listing are closed together. Reporters are thanked; the poster is never told who reported.</div></form>
            </div></div>
            <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}" class="card card-body shadow-sm">@csrf
                <textarea name="note" rows="2" class="form-control mb-2" maxlength="500" placeholder="Why this is not a breach (internal)"></textarea>
                <button class="btn btn-outline-secondary">Dismiss this report</button></form>
        @else<div class="card card-body shadow-sm small">Handled {{ $report->handled_at?->diffForHumans() }}. {{ $report->admin_note }}</div>@endif
    </div>
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Other reports on this listing</div>
        <ul class="list-group list-group-flush small">@forelse($siblings as $s)<li class="list-group-item">{{ $s->reasonLabel() }} · {{ $s->reporter?->name }} · {{ $s->status }}</li>@empty<li class="list-group-item text-muted">None.</li>@endforelse</ul></div></div>
</div>
@endsection
