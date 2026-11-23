@extends('layouts.admin')
@section('title', 'Reports')
@section('content')
<h1 class="h4 mb-3">Listing reports</h1>
@if($repeat->isNotEmpty())
    <div class="alert alert-danger"><strong>Posters with many reports in 30 days:</strong>
        @foreach($repeat as $r)<a class="ms-2" href="{{ route('admin.users.show', $r->id) }}">{{ $r->name }} ({{ $r->total }})</a>@endforeach</div>
@endif
<form class="d-flex flex-wrap gap-2 mb-3" method="GET">
    @foreach(['open' => 'Open', 'actioned' => 'Actioned', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $label)<a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.reports.index', ['status' => $k]) }}">{{ $label }}</a>@endforeach
    <input type="hidden" name="status" value="{{ $status }}">
    <select name="reason" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">Any reason</option>@foreach(config('trust.report_reasons') as $k => $label)<option value="{{ $k }}" @selected(request('reason') === $k)>{{ $label }}</option>@endforeach</select>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Listing</th><th>Reason</th><th>Reporter</th><th>Open on listing</th><th>Status</th><th>Reported</th><th></th></tr></thead><tbody>
    @forelse($reports as $r)
        <tr><td>{{ $r->property->title }}<div class="small text-muted">poster: {{ $r->property->user->name }}</div></td><td>{{ $r->reasonLabel() }}</td><td>{{ $r->reporter?->name }}</td>
            <td>@if(($openPerProperty[$r->property_id] ?? 0) >= 2)<span class="badge text-bg-danger">{{ $openPerProperty[$r->property_id] }}</span>@else{{ $openPerProperty[$r->property_id] ?? 0 }}@endif</td>
            <td><span class="badge text-bg-light border">{{ $r->status }}</span></td><td class="small">{{ $r->created_at->diffForHumans() }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.reports.show', $r) }}">Review</a></td></tr>
    @empty<tr><td colspan="7" class="text-muted">No reports.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $reports->links('pagination::bootstrap-5') }}</div>
@endsection
