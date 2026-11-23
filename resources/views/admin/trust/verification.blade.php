@extends('layouts.admin')
@section('title', 'Review verification')
@section('content')
<a href="{{ route('admin.verifications.index') }}" class="small">&larr; Verifications</a>
<h1 class="h4 my-2">{{ $request->label() }} <span class="badge text-bg-secondary fs-6">{{ $request->status }}</span></h1>
<p class="text-muted">{{ $request->user->name }} · {{ $request->user->email ?? $request->user->mobile }}
    @if($request->property) · <a href="{{ route('admin.properties.show', $request->property) }}">{{ $request->property->title }}</a>@endif</p>
<div class="alert alert-warning small">Review that the documents are genuine and match the applicant. Approving shows the badge "{{ config('trust.types.'.$request->type.'.explain') }}" It is <strong>not</strong> a legal title check.</div>
@if($request->applicant_note)<p><strong>Note from applicant:</strong> {{ $request->applicant_note }}</p>@endif
<div class="row g-3">
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header">Documents</div>
        <ul class="list-group list-group-flush">
        @forelse($request->documents as $d)
            <li class="list-group-item d-flex justify-content-between"><span>{{ $d->label() }} <span class="text-muted small">({{ $d->original_name }}, {{ $d->size_kb }} KB)</span></span>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.verifications.download', $d) }}">Download</a></li>
        @empty<li class="list-group-item text-muted">The files were deleted after the retention period.</li>@endforelse
        </ul></div></div>
    <div class="col-lg-5">
        @if($request->status === 'pending')
            <div class="card shadow-sm mb-3"><div class="card-body">
                <form method="POST" action="{{ route('admin.verifications.approve', $request) }}" class="mb-3">@csrf<button class="btn btn-success w-100">Approve (valid {{ config('trust.validity_days') }} days)</button></form>
                <form method="POST" action="{{ route('admin.verifications.reject', $request) }}">@csrf
                    <textarea name="reason" rows="3" class="form-control mb-2" required minlength="10" maxlength="500" placeholder="Reason shown to the applicant"></textarea>
                    <button class="btn btn-outline-danger w-100">Reject</button></form>
            </div></div>
        @elseif($request->status === 'approved')
            <div class="card shadow-sm"><div class="card-body"><p class="small">Valid until {{ $request->expires_at?->timezone(config('marketplace.timezone'))->format('d M Y') }}.</p>
                <form method="POST" action="{{ route('admin.verifications.revoke', $request) }}">@csrf
                    <textarea name="reason" rows="2" class="form-control mb-2" required minlength="10" maxlength="500" placeholder="Reason for removing the badge"></textarea>
                    <button class="btn btn-outline-danger w-100">Remove verification</button></form></div></div>
        @else<div class="text-muted small">{{ $request->reject_reason }}</div>@endif
    </div>
</div>
@endsection
