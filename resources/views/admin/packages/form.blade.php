@extends('layouts.admin')
@section('title', $package->exists ? 'Edit package' : 'New package')
@section('content')
<h1 class="h4 mb-3">{{ $package->exists ? 'Edit '.$package->name : 'New package' }}</h1>
<form method="POST" action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}">
    @csrf @if($package->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Details & pricing</div><div class="card-body row g-3">
            <div class="col-md-6"><label class="form-label">Category</label>
                <select name="role_id" class="form-select" required>
                    @foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $package->role_id) == $r->id)>{{ $r->name }}</option>@endforeach
                </select></div>
            <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $package->name) }}" required></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control">{{ old('description', $package->description) }}</textarea></div>
            <div class="col-md-4"><label class="form-label">Price (₹)</label><input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $package->price ?? 0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Discounted price</label><input type="number" step="0.01" min="0" name="discounted_price" class="form-control" value="{{ old('discounted_price', $package->discounted_price) }}"></div>
            <div class="col-md-4"><label class="form-label">GST %</label><input type="number" step="0.01" name="gst_percent" class="form-control" value="{{ old('gst_percent', $package->gst_percent) }}" required></div>
            <div class="col-md-3"><label class="form-label">Validity (days)</label><input type="number" min="1" name="validity_days" class="form-control" value="{{ old('validity_days', $package->validity_days) }}"></div>
            <div class="col-md-3"><label class="form-label">Grace (days)</label><input type="number" min="0" name="grace_days" class="form-control" value="{{ old('grace_days', $package->grace_days) }}" required></div>
            <div class="col-md-3"><label class="form-label">Trial (days)</label><input type="number" min="0" name="trial_days" class="form-control" value="{{ old('trial_days', $package->trial_days) }}" required></div>
            <div class="col-md-3"><label class="form-label">Renewal disc. %</label><input type="number" step="0.01" name="renewal_discount_percent" class="form-control" value="{{ old('renewal_discount_percent', $package->renewal_discount_percent ?? 0) }}"></div>
            <div class="col-md-4"><label class="form-label">Display order</label><input type="number" min="0" name="display_order" class="form-control" value="{{ old('display_order', $package->display_order) }}" required></div>
            <div class="col-md-4"><label class="form-label">Colour / theme</label><input name="color" class="form-control" placeholder="#0d6efd" value="{{ old('color', $package->color) }}"></div>
            <div class="col-md-4"><label class="form-label">Status</label>
                <select name="status" class="form-select"><option value="active" @selected(old('status', $package->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $package->status) === 'inactive')>Inactive</option></select></div>
            <div class="col-12">
                @foreach(['is_free' => 'Free package (price forced to ₹0)', 'is_default' => 'Default fallback for this category (free packages only)', 'is_recommended' => 'Show "recommended" badge', 'auto_renew' => 'Auto-renewal available', 'refund_eligible' => 'Refund eligible'] as $field => $label)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" @checked(old($field, $package->$field))><label class="form-check-label" for="{{ $field }}">{{ $label }}</label></div>
                @endforeach
                @if($package->is_default)<div class="form-text">This is the category's fallback package. To change it, mark another free package as default.</div>@endif
            </div>
            <div class="col-12"><label class="form-label">Terms and conditions</label><textarea name="terms" rows="3" class="form-control">{{ old('terms', $package->terms) }}</textarea></div>
        </div></div></div>

        <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header">Feature limits & permissions</div><div class="card-body">
            @foreach($features as $f)
                @php
                    $v = $values[$f->id] ?? null;
                    $val = old("features.{$f->id}.value", $v ? $v->value : $f->default_value);
                    $unl = old("features.{$f->id}.unlimited", $v?->is_unlimited);
                @endphp
                <div class="row align-items-center py-2 border-bottom">
                    <div class="col-md-5"><strong>{{ $f->name }}</strong><br><code>{{ $f->key }}</code>
                        @if($f->is_daily)<span class="badge text-bg-info">daily</span>@endif</div>
                    <div class="col-md-7">
                        @if($f->type === 'boolean')
                            <input type="hidden" name="features[{{ $f->id }}][value]" value="0">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="features[{{ $f->id }}][value]" value="1" @checked($val === '1' || $val === 1)></div>
                        @elseif($f->type === 'limit')
                            <div class="input-group input-group-sm">
                                <input type="number" min="0" name="features[{{ $f->id }}][value]" class="form-control" value="{{ $unl ? '' : $val }}">
                                <div class="input-group-text"><input class="form-check-input mt-0 me-1" type="checkbox" name="features[{{ $f->id }}][unlimited]" value="1" @checked($unl)> Unlimited</div>
                            </div>
                        @else
                            <select name="features[{{ $f->id }}][value]" class="form-select form-select-sm">
                                @foreach($f->options ?? [] as $opt)<option value="{{ $opt }}" @selected($val === $opt)>{{ $opt }}</option>@endforeach
                            </select>
                        @endif
                    </div>
                </div>
            @endforeach
        </div></div></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save package</button> <a href="{{ route('admin.packages.index') }}" class="btn btn-link">Cancel</a></div>
</form>
@endsection
