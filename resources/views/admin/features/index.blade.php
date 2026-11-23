@extends('layouts.admin')
@section('title', 'Features')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Feature registry</h1>
    <a href="{{ route('admin.features.create') }}" class="btn btn-primary btn-sm">New feature</a>
</div>
<div class="alert alert-info small">A new feature appears in every package form immediately, and packages without a value use the feature's default. It only takes effect once application code checks its key through <code>PermissionService</code>.</div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Key</th><th>Name</th><th>Type</th><th>Default</th><th>Daily</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach($features as $f)
        <tr><td><code>{{ $f->key }}</code></td><td>{{ $f->name }}</td><td>{{ $f->type }}</td><td>{{ $f->default_value }}</td>
            <td>{{ $f->is_daily ? 'Yes' : '' }}</td>
            <td><span class="badge text-bg-{{ $f->is_active ? 'success' : 'secondary' }}">{{ $f->is_active ? 'active' : 'inactive' }}</span></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.features.edit', $f) }}">Edit</a></td></tr>
    @endforeach
    </tbody></table></div></div>
@endsection
