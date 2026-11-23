<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; width: 220px; }
        .sidebar .nav-link { color: #cbd5e1; border-radius: .4rem; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: #334155; color: #fff; }
        code { font-size: .8em; }
    </style>
</head>
<body class="bg-light">
<div class="d-flex flex-column flex-md-row">
    <nav class="sidebar bg-dark text-white p-3 flex-shrink-0">
        <div class="fw-bold mb-3">{{ config('app.name') }}</div>
        <ul class="nav nav-pills flex-column gap-1">
            <li><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a class="nav-link {{ request()->routeIs('admin.properties.*') ? 'active' : '' }}" href="{{ route('admin.properties.index') }}">Properties</a></li>
            <li><a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a></li>
            @if(auth('admin')->user()?->isSuper())
                <li><a class="nav-link {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}" href="{{ route('admin.packages.index') }}">Packages</a></li>
                <li><a class="nav-link {{ request()->routeIs('admin.features.*') ? 'active' : '' }}" href="{{ route('admin.features.index') }}">Features</a></li>
                <li><a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">Notifications</a></li>
                <li class="text-secondary small mt-2 px-2">Finance</li>
                <li><a class="nav-link {{ request()->routeIs('admin.finance') ? 'active' : '' }}" href="{{ route('admin.finance') }}">Revenue</a></li>
                <li><a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">Payments</a></li>
                <li><a class="nav-link {{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}" href="{{ route('admin.refunds.index') }}">Refunds</a></li>
                <li><a class="nav-link {{ request()->is('admin/billing/coupons') ? 'active' : '' }}" href="{{ route('admin.billing.index', 'coupons') }}">Coupons</a></li>
                <li><a class="nav-link {{ request()->is('admin/billing/credit-packs') ? 'active' : '' }}" href="{{ route('admin.billing.index', 'credit-packs') }}">Credit packs</a></li>
                <li><a class="nav-link {{ request()->is('admin/billing/promotion-packages') ? 'active' : '' }}" href="{{ route('admin.billing.index', 'promotion-packages') }}">Promotions</a></li>
                <li class="text-secondary small mt-2 px-2">Catalogue</li>
                <li><a class="nav-link {{ request()->is('admin/catalog/categories') ? 'active' : '' }}" href="{{ route('admin.catalog.index', 'categories') }}">Categories</a></li>
                <li><a class="nav-link {{ request()->is('admin/catalog/amenities') ? 'active' : '' }}" href="{{ route('admin.catalog.index', 'amenities') }}">Amenities</a></li>
                <li><a class="nav-link {{ request()->is('admin/catalog/locations') ? 'active' : '' }}" href="{{ route('admin.catalog.index', 'locations') }}">Locations</a></li>
            @endif
            <li><a class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ route('admin.audit.index') }}">Audit log</a></li>
        </ul>
        <hr class="border-secondary">
        <div class="small text-secondary mb-2">{{ auth('admin')->user()?->name }}</div>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf
            <button class="btn btn-sm btn-outline-light w-100">Log out</button>
        </form>
    </nav>
    <main class="flex-grow-1 p-4">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
