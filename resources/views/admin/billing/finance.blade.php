@extends('layouts.admin')
@section('title', 'Revenue')
@section('content')
@php $inr = fn ($p) => '₹'.number_format($p / 100, 2); @endphp
<h1 class="h4 mb-3">Revenue</h1>
<form class="row g-2 mb-4" method="GET">
    <div class="col-auto"><label class="form-label small mb-0">From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small mb-0">To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm"></div>
    <div class="col-auto align-self-end"><button class="btn btn-sm btn-primary">Show</button>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.payments.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">Export CSV</a></div>
</form>
<div class="row g-3 mb-4">
    @foreach([['Net revenue (incl. GST)', $inr($net)], ['Taxable value', $inr($taxable)], ['GST collected', $inr($tax)], ['Discounts given', $inr($discounts)], ['Paid payments', $count], ['Refunded', $inr($refundedTotal).' ('.$refundedCount.')']] as [$label, $value])
        <div class="col-6 col-lg-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-4 fw-semibold">{{ $value }}</div></div></div></div>
    @endforeach
</div>
<div class="card shadow-sm" style="max-width:520px"><div class="card-header">By purchase type</div>
    <table class="table mb-0"><tbody>@forelse($byPurpose as $row)<tr><td>{{ ucfirst(str_replace('_', ' ', $row->purpose)) }}</td><td>{{ $row->n }}</td><td class="text-end">{{ $inr((int) $row->total) }}</td></tr>@empty<tr><td class="text-muted">No payments in this period.</td></tr>@endforelse</tbody></table></div>
<p class="small text-muted mt-3">Net revenue counts paid payments only; fully refunded payments are listed separately. Dates are India time. Verify GST treatment with your accountant.</p>
@endsection
