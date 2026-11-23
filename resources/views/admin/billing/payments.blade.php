@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Payments</h1>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.payments.export', request()->only(['from', 'to'])) }}">Export CSV (this month)</a></div>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Transaction, gateway id, name or email"></div>
    <div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">Any status</option>@foreach(['created', 'paid', 'failed', 'refunded'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="purpose" class="form-select form-select-sm"><option value="">Any purpose</option>@foreach(['subscription', 'credit_pack', 'promotion'] as $s)<option value="{{ $s }}" @selected(request('purpose') === $s)>{{ str_replace('_', ' ', $s) }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Date</th><th>Transaction</th><th>Customer</th><th>Purchase</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($payments as $p)
        <tr><td class="small">{{ ($p->paid_at ?? $p->created_at)->timezone(config('marketplace.timezone'))->format('d M Y H:i') }}</td><td><code>{{ $p->txn_id }}</code></td>
            <td>{{ $p->user->name }}</td><td class="small">{{ $p->description }}</td><td class="text-end">{{ $p->total() }}</td>
            <td><span class="badge text-bg-{{ ['paid' => 'success', 'refunded' => 'secondary', 'failed' => 'danger', 'created' => 'light'][$p->status] ?? 'light' }} border">{{ $p->status }}</span></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.payments.show', $p) }}">Open</a></td></tr>
    @empty<tr><td colspan="7" class="text-muted">No payments.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $payments->links('pagination::bootstrap-5') }}</div>
@endsection
