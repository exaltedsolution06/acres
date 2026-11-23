@extends('layouts.admin')
@section('title', 'Packages')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Packages</h1>
    <a href="{{ route('admin.packages.create') }}" class="btn btn-primary btn-sm">New package</a>
</div>
<form class="mb-3" method="GET">
    <select name="role" class="form-select form-select-sm w-auto d-inline" onchange="this.form.submit()">
        <option value="">All categories</option>
        @foreach($roles as $r)<option value="{{ $r->id }}" @selected(request('role') == $r->id)>{{ $r->name }}</option>@endforeach
    </select>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Order</th><th>Category</th><th>Package</th><th>Price</th><th>Validity</th><th>Active subs</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($packages as $p)
        <tr>
            <td>{{ $p->display_order }}</td><td>{{ $p->role->name }}</td>
            <td>{{ $p->name }}
                @if($p->is_default)<span class="badge text-bg-secondary">default</span>@endif
                @if($p->is_recommended)<span class="badge text-bg-warning">recommended</span>@endif</td>
            <td>@if($p->is_free)Free @else ₹{{ number_format($p->price) }}@if($p->discounted_price) <small class="text-success">(₹{{ number_format($p->discounted_price) }})</small>@endif @endif</td>
            <td>{{ $p->validity_days ? $p->validity_days.' days' : '—' }}</td>
            <td>{{ $activeCounts[$p->id] ?? 0 }}</td>
            <td><span class="badge text-bg-{{ $p->status === 'active' ? 'success' : 'secondary' }}">{{ $p->status }}</span></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.packages.edit', $p) }}">Edit</a>
                <form class="d-inline" method="POST" action="{{ route('admin.packages.duplicate', $p) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Duplicate</button></form>
                @unless($p->is_default)
                <form class="d-inline" method="POST" action="{{ route('admin.packages.toggle', $p) }}">@csrf<button class="btn btn-sm btn-outline-warning">{{ $p->status === 'active' ? 'Disable' : 'Enable' }}</button></form>
                <form class="d-inline" method="POST" action="{{ route('admin.packages.destroy', $p) }}" onsubmit="return confirm('Delete this package?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                @endunless
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-muted">No packages.</td></tr>
    @endforelse
    </tbody></table></div></div>
@endsection
