@extends('layouts.admin')
@section('title', 'Properties')
@section('content')
<h1 class="h4 mb-3">Property moderation</h1>
<div class="mb-3 d-flex flex-wrap gap-2">
    @foreach(['pending_approval' => 'Pending', 'published' => 'Published', 'rejected' => 'Rejected', 'paused' => 'Paused', 'expired' => 'Expired', 'sold' => 'Sold', 'rented' => 'Rented', 'draft' => 'Drafts', 'archived' => 'Archived', 'all' => 'All'] as $key => $label)
        <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.properties.index', ['status' => $key]) }}">{{ $label }}@if($key !== 'all' && ($counts[$key] ?? 0)) ({{ $counts[$key] }})@endif</a>
    @endforeach
</div>
<form class="d-flex gap-2 mb-3" method="GET"><input type="hidden" name="status" value="{{ $status }}">
    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm w-auto" placeholder="Title or listing ID">
    <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="duplicates" value="1" id="dups" @checked(request('duplicates'))><label class="form-check-label small" for="dups">Possible duplicates only</label></div>
    <button class="btn btn-sm btn-outline-secondary">Filter</button></form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Listing</th><th>Poster</th><th>Price</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
    @forelse($properties as $p)
        <tr><td><div class="fw-semibold">{{ $p->title }}</div><div class="small text-muted">{{ $p->typeLabel() }} · {{ $p->locality->name }}, {{ $p->city->name }} · <code>{{ $p->public_id }}</code></div>
            @if($p->duplicate_of_id)<span class="badge text-bg-warning">possible duplicate</span>@endif</td>
            <td>{{ $p->user->name }}<div class="small text-muted">{{ $p->posted_by }}</div></td>
            <td>{{ $p->displayPrice() }}</td><td>{{ str_replace('_', ' ', $p->status) }}</td><td>{{ $p->updated_at->diffForHumans() }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.properties.show', $p) }}">Review</a></td></tr>
    @empty<tr><td colspan="6" class="text-muted">Nothing here.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $properties->links('pagination::bootstrap-5') }}</div>
@endsection
