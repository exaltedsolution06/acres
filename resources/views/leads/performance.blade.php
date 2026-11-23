@extends('layouts.app')
@section('title', 'Lead performance')
@section('content')
<a href="{{ route('leads.index') }}" class="small">&larr; Leads</a>
<h1 class="h4 my-2">Lead performance</h1>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Total leads</div><div class="fs-4">{{ $total }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Converted</div><div class="fs-4">{{ $byStatus['converted'] ?? 0 }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Conversion rate</div><div class="fs-4">{{ $conversion }}%</div></div></div></div>
</div>
<div class="row g-3">
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header">By stage</div><table class="table mb-0"><tbody>
        @foreach(config('engagement.statuses') as $key => $label)<tr><td>{{ $label }}</td><td class="text-end">{{ $byStatus[$key] ?? 0 }}</td></tr>@endforeach</tbody></table></div></div>
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Most enquired listings</div><table class="table mb-0"><tbody>
        @forelse($topProperties as $p)<tr><td>{{ $p->title }}</td><td class="text-end">{{ $p->total }}</td></tr>@empty<tr><td class="text-muted">No data yet.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
