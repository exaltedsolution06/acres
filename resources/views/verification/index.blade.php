@extends('layouts.app')
@section('title', 'Verification')
@section('content')
@php $statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'revoked' => 'dark', 'expired' => 'secondary']; @endphp
<h1 class="h4 mb-1">Verification</h1>
<p class="text-muted">Badges tell visitors exactly what we checked. They do not verify legal title. Approved badges last {{ config('trust.validity_days') }} days.</p>
<div class="alert alert-info small">Your documents are stored privately, seen only by our review team, and deleted {{ $retention }} days after we decide.</div>

<div class="row g-3 mb-4">
@foreach($types as $key => $type)
    <div class="col-lg-6"><div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between"><span>{{ $type['label'] }}</span>
            @if(($until[$key] ?? null))<span class="badge text-bg-success">Active until {{ $until[$key]->timezone(config('marketplace.timezone'))->format('d M Y') }}</span>@endif</div>
        <div class="card-body">
            <p class="small text-muted">{{ $type['explain'] }}</p>
            <form method="POST" action="{{ route('verification.store') }}" enctype="multipart/form-data">@csrf
                <input type="hidden" name="type" value="{{ $key }}">
                @if($key === 'property_documents')
                    <div class="mb-2"><label class="form-label small">Listing</label>
                        <select name="property" class="form-select form-select-sm" required><option value="">Choose a listing</option>@foreach($properties as $p)<option value="{{ $p->public_id }}">{{ \Illuminate\Support\Str::limit($p->title, 60) }}</option>@endforeach</select></div>
                @endif
                @foreach($type['documents'] as $docKey => $docLabel)
                    <div class="mb-2"><label class="form-label small">{{ $docLabel }}@if(in_array($docKey, $type['required'])) <span class="text-danger">*</span>@endif</label>
                        <input type="file" name="documents[{{ $docKey }}]" accept="application/pdf,image/jpeg,image/png" class="form-control form-control-sm"></div>
                @endforeach
                <div class="mb-2"><textarea name="note" rows="2" class="form-control form-control-sm" maxlength="500" placeholder="Note for the reviewer (optional)"></textarea></div>
                <button class="btn btn-sm btn-primary">Submit for review</button>
                <div class="form-text">PDF, JPG or PNG, up to {{ config('trust.max_file_kb') / 1024 }} MB each.</div>
            </form>
        </div>
    </div></div>
@endforeach
</div>

<h2 class="h5">Your requests</h2>
<div class="vstack gap-2">
@forelse($requests as $r)
    <div class="card shadow-sm"><div class="card-body d-flex justify-content-between flex-wrap gap-2">
        <div><strong>{{ $r->label() }}</strong> @if($r->property)<span class="text-muted">· {{ \Illuminate\Support\Str::limit($r->property->title, 50) }}</span>@endif
            <div class="small text-muted">Sent {{ $r->created_at->diffForHumans() }}@if($r->expires_at && $r->status === 'approved') · valid until {{ $r->expires_at->timezone(config('marketplace.timezone'))->format('d M Y') }}@endif</div>
            @if($r->reject_reason)<div class="small text-danger">{{ $r->reject_reason }}</div>@endif
            <div class="small mt-1">@foreach($r->documents as $d)<a class="me-2" href="{{ route('verification.download', $d) }}">{{ $d->label() }}</a>@endforeach</div></div>
        <span class="badge text-bg-{{ $statusBadge[$r->status] ?? 'light' }} align-self-start">{{ $r->status }}</span>
    </div></div>
@empty<div class="text-muted">No requests yet.</div>@endforelse
</div>
@endsection
