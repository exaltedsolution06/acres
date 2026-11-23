@extends('layouts.admin')
@section('title', 'Audit log')
@section('content')
<h1 class="h4 mb-3">Audit log</h1>
<form class="mb-3" method="GET"><input name="action" value="{{ request('action') }}" class="form-control form-control-sm w-auto d-inline" placeholder="Filter, e.g. package"> <button class="btn btn-sm btn-outline-secondary">Filter</button></form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Subject</th><th>Change</th><th>IP</th></tr></thead><tbody>
    @forelse($logs as $log)
        <tr><td class="text-nowrap">{{ $log->created_at->timezone(config('marketplace.timezone'))->format('d M Y H:i') }}</td>
            <td>{{ $log->admin?->name ?? '—' }}</td><td><code>{{ $log->action }}</code></td>
            <td>{{ $log->subject_type }} #{{ $log->subject_id }}</td>
            <td class="small"><details><summary>View</summary><pre class="mb-0 small">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details></td>
            <td>{{ $log->ip }}</td></tr>
    @empty<tr><td colspan="6" class="text-muted">No entries.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
