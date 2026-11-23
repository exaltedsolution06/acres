@extends('layouts.app')
@section('title', 'Alerts')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Alerts</h1>
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark all read</button></form></div>
<ul class="list-group shadow-sm">
@forelse($notifications as $n)
    <li class="list-group-item {{ $n->read_at ? '' : 'bg-light' }}">
        <a href="{{ route('notifications.open', $n->id) }}" class="text-decoration-none text-dark d-block">
            <div class="d-flex justify-content-between"><strong>{{ $n->data['title'] ?? 'Notification' }}</strong><span class="small text-muted">{{ $n->created_at->diffForHumans() }}</span></div>
            <div class="small text-muted">{{ $n->data['body'] ?? '' }}</div>
        </a>
    </li>
@empty<li class="list-group-item text-muted">Nothing yet.</li>@endforelse
</ul>
<div class="mt-3">{{ $notifications->links('pagination::bootstrap-5') }}</div>
@endsection
