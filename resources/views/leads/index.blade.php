@extends('layouts.app')
@section('title', 'Leads')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Leads</h1>
    <a href="{{ route('leads.performance') }}" class="btn btn-sm btn-outline-secondary">Performance (broker CRM)</a>
</div>
<div class="mb-3 d-flex flex-wrap gap-2">
    <a class="btn btn-sm {{ ! request('status') && ! request('due') ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('leads.index') }}">All</a>
    <a class="btn btn-sm {{ request('due') ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('leads.index', ['due' => 1]) }}">Follow-ups due</a>
    @foreach(config('engagement.statuses') as $key => $label)
        <a class="btn btn-sm {{ request('status') === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('leads.index', ['status' => $key]) }}">{{ $label }}@if($counts[$key] ?? 0) ({{ $counts[$key] }})@endif</a>
    @endforeach
</div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Visitor</th><th>Listing</th><th>What they did</th><th>Status</th><th>Last activity</th><th></th></tr></thead><tbody>
    @forelse($leads as $l)
        <tr><td>{{ $l->seeker->name }}</td>
            <td>{{ $l->property->title }}</td>
            <td class="small">@if($l->interest_at)Interest @endif @if($l->enquired_at)· Enquiry @endif</td>
            <td><span class="badge text-bg-light border">{{ config('engagement.statuses')[$l->status] ?? $l->status }}</span>
                @if($l->follow_up_at)<div class="small text-warning">Follow up {{ $l->follow_up_at->timezone(config('marketplace.timezone'))->format('d M, h:i A') }}</div>@endif</td>
            <td class="small">{{ $l->last_activity_at?->diffForHumans() }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('leads.show', $l) }}">Open</a></td></tr>
    @empty
        <tr><td colspan="6" class="text-muted">No leads yet. They appear when people show interest in or enquire about your listings.</td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $leads->links('pagination::bootstrap-5') }}</div>
@endsection
