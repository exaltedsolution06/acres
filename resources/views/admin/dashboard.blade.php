@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<h1 class="h4 mb-3">Dashboard</h1>
<div class="row g-3 mb-4">
    @foreach([
        ['Total users', $totalUsers], ['Suspended', $suspended], ['Registrations today', $registrationsToday],
        ['Registrations (7 days)', $registrations7d], ['Verifications waiting', $pendingVerifications], ['Open reports', $openReports], ['Active subscriptions', $activeSubs], ['Expired subscriptions', $expiredSubs],
        ['Revenue (all time)', '₹'.number_format($revenueTotal, 2)], ['Revenue (this month)', '₹'.number_format($revenueMonth, 2)],
    ] as [$label, $value])
        <div class="col-6 col-lg-3"><div class="card h-100 shadow-sm"><div class="card-body">
            <div class="text-muted small">{{ $label }}</div><div class="fs-4 fw-semibold">{{ $value }}</div>
        </div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Users by role</div>
        <table class="table mb-0"><tbody>@foreach($roleCounts as $r)<tr><td>{{ $r->name }}</td><td class="text-end">{{ $r->total }}</td></tr>@endforeach</tbody></table>
        <div class="card-footer small text-muted">A user with several roles is counted once per role.</div></div></div>
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Top active packages</div>
        <table class="table mb-0"><tbody>@forelse($topPackages as $p)<tr><td>{{ $p->name }}</td><td class="text-end">{{ $p->total }}</td></tr>@empty<tr><td class="text-muted">No subscriptions yet.</td></tr>@endforelse</tbody></table></div></div>
</div>
<p class="small text-muted mt-3">Revenue is the total of paid payments (plans, credit packs and promotions, GST included); refunded payments are excluded. See Finance for the breakdown.</p>
@endsection
