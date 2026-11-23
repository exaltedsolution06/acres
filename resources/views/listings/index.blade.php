@extends('layouts.app')
@section('title', 'My listings')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">My listings</h1>
    @if($canPost)<a href="{{ route('listings.create') }}" class="btn btn-primary btn-sm">Post a property</a>@endif
</div>

@unless($canPost)
    <div class="card card-body shadow-sm mb-3">
        <p class="mb-2">To post properties, add the Owner or Broker role to your account. You keep one account and can switch roles any time.</p>
        <form method="POST" action="{{ route('role.add') }}" class="d-flex gap-2">@csrf
            <button name="role" value="owner" class="btn btn-sm btn-outline-primary">Add Owner role</button>
            <button name="role" value="broker" class="btn btn-sm btn-outline-primary">Add Broker role</button>
        </form>
    </div>
@endunless

<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Listing</th><th>Status</th><th>Price</th><th>Views</th><th>Valid until</th><th></th></tr></thead><tbody>
    @forelse($properties as $p)
        @php $badge = ['published' => 'success', 'pending_approval' => 'warning', 'rejected' => 'danger', 'paused' => 'secondary', 'expired' => 'dark', 'sold' => 'info', 'rented' => 'info', 'draft' => 'light', 'archived' => 'light'][$p->status] ?? 'light'; @endphp
        <tr>
            <td><div class="fw-semibold">{{ $p->title }}</div><div class="small text-muted">{{ $p->typeLabel() }} · {{ $p->locality->name }}, {{ $p->city->name }}</div>
                @if($p->status === 'rejected' && $p->rejection_reason)<div class="small text-danger">Rejected: {{ $p->rejection_reason }}</div>@endif</td>
            <td><span class="badge text-bg-{{ $badge }} border">{{ str_replace('_', ' ', $p->status) }}</span></td>
            <td>{{ $p->displayPrice() }}</td><td>{{ $p->views_count }}</td>
            <td>{{ $p->expires_at?->timezone(config('marketplace.timezone'))->format('d M Y') ?? '—' }}</td>
            <td class="text-end text-nowrap">
                @if($p->status === 'published')<a class="btn btn-sm btn-outline-secondary" href="{{ route('property.show', $p->slug) }}">View</a> <a class="btn btn-sm btn-outline-warning" href="{{ route('promote', $p) }}">Promote</a>@endif
                @unless(in_array($p->status, ['sold', 'rented', 'archived']))<a class="btn btn-sm btn-outline-primary" href="{{ route('listings.edit', $p) }}">Edit</a>@endunless
                @foreach([
                    'submit' => in_array($p->status, ['draft', 'rejected', 'expired']) ? 'Submit' : null,
                    'pause' => $p->status === 'published' ? 'Pause' : null,
                    'resume' => $p->status === 'paused' ? 'Resume' : null,
                    'renew' => in_array($p->status, ['published', 'expired']) ? 'Renew' : null,
                    'sold' => $p->purpose === 'sell' && in_array($p->status, ['published', 'paused']) ? 'Sold' : null,
                    'rented' => $p->purpose !== 'sell' && in_array($p->status, ['published', 'paused']) ? 'Rented' : null,
                    'archive' => ! in_array($p->status, ['archived', 'sold', 'rented']) ? 'Archive' : null,
                ] as $action => $label)
                    @if($label)<form class="d-inline" method="POST" action="{{ route('listings.'.$action, $p) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $label }}</button></form>@endif
                @endforeach
                @if($p->status === 'draft')<form class="d-inline" method="POST" action="{{ route('listings.destroy', $p) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-muted">You have not posted anything yet.</td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $properties->links('pagination::bootstrap-5') }}</div>
@endsection
