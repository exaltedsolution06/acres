@extends('layouts.app')
@section('title', 'Saved searches')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Saved searches</h1><a href="{{ route('saved.properties') }}" class="btn btn-sm btn-outline-secondary">Saved properties</a></div>
<div class="alert alert-info small">We check once a day and notify you about new listings that match.</div>
<ul class="list-group shadow-sm">
@forelse($searches as $s)
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <div><a class="fw-semibold" href="{{ url('/search?'.http_build_query($s->filters)) }}">{{ $s->name }}</a><div class="small text-muted">Saved {{ $s->created_at->diffForHumans() }}</div></div>
        <form method="POST" action="{{ route('saved.searches.destroy', $s) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </li>
@empty<li class="list-group-item text-muted">No saved searches. Use "Save search" on the search page.</li>@endforelse
</ul>
@endsection
