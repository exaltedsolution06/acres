@extends('layouts.admin')
@section('title', $user->name)
@section('content')
<a href="{{ route('admin.users.index') }}" class="small">&larr; Users</a>
<h1 class="h4 my-2">{{ $user->name }} <span class="badge text-bg-{{ $user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'danger' : 'warning') }}">{{ $user->status }}</span></h1>
<p class="text-muted">{{ $user->email }} · {{ $user->mobile ?? 'no mobile' }} · Contact credits: <strong>{{ $credits }}</strong></p>

<div class="row g-3">
    <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header">Account status</div><div class="card-body">
        <form method="POST" action="{{ route('admin.users.status', $user) }}" class="d-flex gap-2">@csrf
            <select name="status" class="form-select form-select-sm">@foreach(['active', 'restricted', 'suspended'] as $s)<option value="{{ $s }}" @selected($user->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
            <button class="btn btn-sm btn-primary">Update</button></form>
        <div class="form-text">Suspended and restricted accounts get no feature access and no allowance.</div>
    </div></div></div>

    <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header">Roles</div><div class="card-body">
        @foreach($user->roles as $r)
            <form class="d-inline" method="POST" action="{{ route('admin.users.roles.remove', [$user, $r]) }}" onsubmit="return confirm('Remove this role?')">@csrf @method('DELETE')
                <span class="badge text-bg-light border fs-6">{{ $r->name }}{{ $user->active_role_id === $r->id ? ' (active)' : '' }}
                    <button class="btn btn-sm p-0 ms-1 text-danger" title="Remove">&times;</button></span></form>
        @endforeach
        <form method="POST" action="{{ route('admin.users.roles.add', $user) }}" class="d-flex gap-2 mt-3">@csrf
            <select name="role" class="form-select form-select-sm">@foreach($allRoles->whereNotIn('id', $user->roles->pluck('id')) as $r)<option value="{{ $r->slug }}">{{ $r->name }}</option>@endforeach</select>
            <button class="btn btn-sm btn-outline-primary">Add role</button></form>
    </div></div></div>

    <div class="col-12"><div class="card shadow-sm"><div class="card-header">Plans</div>
        <table class="table mb-0 align-middle"><thead><tr><th>Role</th><th>Effective package</th><th>Subscription</th></tr></thead><tbody>
        @forelse($plans as $p)
            <tr><td>{{ $p['role']->name }}</td><td>{{ $p['package']?->name ?? '—' }}</td>
                <td>@if($p['subscription'])Until {{ $p['subscription']->ends_at?->timezone(config('marketplace.timezone'))->format('d M Y') ?? 'no expiry' }}@else<span class="text-muted">None (default package)</span>@endif</td></tr>
        @empty<tr><td colspan="3" class="text-muted">No roles assigned.</td></tr>@endforelse
        </tbody></table>
        <div class="card-body border-top">
            <form method="POST" action="{{ route('admin.users.subscription', $user) }}" class="d-flex gap-2">@csrf
                <select name="package_id" class="form-select form-select-sm">@foreach($packages->whereIn('role_id', $user->roles->pluck('id')) as $pkg)<option value="{{ $pkg->id }}">{{ $pkg->role->name }} · {{ $pkg->name }}</option>@endforeach</select>
                <button class="btn btn-sm btn-outline-primary text-nowrap">Grant (no payment)</button></form>
        </div>
    </div></div>

    <div class="col-12"><div class="card shadow-sm"><div class="card-header d-flex justify-content-between"><span>Today's usage (active role)</span>
        <form method="POST" action="{{ route('admin.users.reset-usage', $user) }}" onsubmit="return confirm('Reset all of today\'s counters?')">@csrf<button class="btn btn-sm btn-outline-danger">Reset all</button></form></div>
        <table class="table mb-0"><thead><tr><th>Feature</th><th>Used</th><th>Bonus</th><th>Limit</th></tr></thead><tbody>
        @forelse($usage as $row)
            <tr><td>{{ $row['feature']->name }}</td><td>{{ $row['used'] }}</td><td>{{ $row['bonus'] }}</td><td>{{ $row['limit'] === null ? 'Unlimited' : $row['limit'] }}</td></tr>
        @empty<tr><td colspan="4" class="text-muted">The user has no active role.</td></tr>@endforelse
        </tbody></table>
        <div class="card-body border-top row g-3">
            <form method="POST" action="{{ route('admin.users.bonus', $user) }}" class="col-md-7 d-flex gap-2">@csrf
                <select name="feature_key" class="form-select form-select-sm">@foreach($dailyFeatures as $f)<option value="{{ $f->key }}">{{ $f->name }}</option>@endforeach</select>
                <input type="number" name="amount" min="1" max="1000" value="5" class="form-control form-control-sm" style="max-width:90px">
                <button class="btn btn-sm btn-outline-primary text-nowrap">Grant bonus today</button></form>
            <form method="POST" action="{{ route('admin.users.credits', $user) }}" class="col-md-5 d-flex gap-2">@csrf
                <input type="number" name="amount" min="1" max="1000" value="10" class="form-control form-control-sm" style="max-width:90px">
                <button class="btn btn-sm btn-outline-primary text-nowrap">Add contact credits</button></form>
        </div>
    </div></div>

    <div class="col-12"><div class="card shadow-sm"><div class="card-header">Subscription history</div>
        <table class="table mb-0"><thead><tr><th>Package</th><th>Status</th><th>Source</th><th>Starts</th><th>Ends</th><th>Paid</th></tr></thead><tbody>
        @forelse($history as $s)<tr><td>{{ $s->package->name }}</td><td>{{ $s->status }}</td><td>{{ $s->source }}</td><td>{{ $s->starts_at->format('d M Y') }}</td><td>{{ $s->ends_at?->format('d M Y') ?? '—' }}</td><td>₹{{ number_format($s->price_paid, 2) }}</td></tr>
        @empty<tr><td colspan="6" class="text-muted">None.</td></tr>@endforelse
        </tbody></table></div></div>
</div>
@endsection
