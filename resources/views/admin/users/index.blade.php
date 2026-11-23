@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<h1 class="h4 mb-3">Users</h1>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, email or mobile"></div>
    <div class="col-auto"><select name="role" class="form-select form-select-sm"><option value="">All roles</option>@foreach($roles as $r)<option value="{{ $r->slug }}" @selected(request('role') === $r->slug)>{{ $r->name }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">Any status</option>@foreach(['active', 'restricted', 'suspended'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Roles</th><th>Status</th><th>Joined</th><th></th></tr></thead><tbody>
    @forelse($users as $u)
        <tr><td>{{ $u->id }}</td><td>{{ $u->name }}</td><td>{{ $u->email }}<br><small class="text-muted">{{ $u->mobile }}</small></td>
            <td>@foreach($u->roles as $r)<span class="badge text-bg-light border">{{ $r->name }}</span> @endforeach</td>
            <td><span class="badge text-bg-{{ $u->status === 'active' ? 'success' : ($u->status === 'suspended' ? 'danger' : 'warning') }}">{{ $u->status }}</span></td>
            <td>{{ $u->created_at?->format('d M Y') }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $u) }}">Manage</a></td></tr>
    @empty<tr><td colspan="7" class="text-muted">No users found.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
