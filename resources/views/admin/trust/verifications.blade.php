@extends('layouts.admin')
@section('title', 'Verifications')
@section('content')
<h1 class="h4 mb-3">Verification requests</h1>
<div class="mb-3 d-flex flex-wrap gap-2">@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'revoked' => 'Revoked', 'expired' => 'Expired', 'all' => 'All'] as $k => $label)
    <a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.verifications.index', ['status' => $k]) }}">{{ $label }}@if($k !== 'all' && ($counts[$k] ?? 0)) ({{ $counts[$k] }})@endif</a>@endforeach</div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Applicant</th><th>Type</th><th>Listing</th><th>Files</th><th>Status</th><th>Sent</th><th></th></tr></thead><tbody>
    @forelse($requests as $r)
        <tr><td>{{ $r->user->name }}</td><td>{{ $r->label() }}</td><td class="small">{{ $r->property?->title }}</td><td>{{ $r->documents_count }}</td>
            <td><span class="badge text-bg-light border">{{ $r->status }}</span></td><td class="small">{{ $r->created_at->diffForHumans() }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.verifications.show', $r) }}">Review</a></td></tr>
    @empty<tr><td colspan="7" class="text-muted">Nothing here.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
@endsection
