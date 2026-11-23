<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @stack('head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>.card-img-top.ph{height:180px;object-fit:cover;background:#e9ecef}</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-md bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-sm btn-link" href="{{ route('search') }}">Search</a>
            <a class="btn btn-sm btn-link" href="{{ route('pricing') }}">Pricing</a>
            @auth
                @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                <a class="btn btn-sm btn-outline-secondary position-relative" href="{{ route('notifications.index') }}">Alerts@if($unread) <span class="badge text-bg-danger">{{ $unread }}</span>@endif</a>
                <a class="btn btn-sm btn-link" href="{{ route('saved.properties') }}">Saved</a>
                <a class="btn btn-sm btn-link" href="{{ route('billing.index') }}">Billing</a>
                <a class="btn btn-sm btn-link" href="{{ route('enquiries.index') }}">Enquiries</a>
                <a class="btn btn-sm btn-link" href="{{ route('requirements.index') }}">Requirements</a>
                <a class="btn btn-sm btn-outline-success" href="{{ route('listings.index') }}">My listings</a>
                <a class="btn btn-sm btn-outline-success" href="{{ route('leads.index') }}">Leads</a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('profile.edit') }}">Profile</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-link text-secondary">Log out</button></form>
            @else
                <a class="btn btn-sm btn-outline-primary" href="{{ route('login') }}">Log in</a>
                <a class="btn btn-sm btn-primary" href="{{ route('register') }}">Register</a>
            @endauth
        </div>
    </div>
</nav>
<main class="container py-4">
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
