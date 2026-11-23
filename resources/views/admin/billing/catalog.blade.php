@extends('layouts.admin')
@section('title', ucfirst(str_replace('-', ' ', $type)))
@section('content')
<h1 class="h4 mb-3">{{ ucfirst(str_replace('-', ' ', $type)) }}</h1>
<div class="alert alert-info small">Amounts are in rupees, excluding GST. Items are switched off rather than deleted, because payments refer to them.@if($type === 'coupons') Coupon codes cannot be changed after creation.@endif</div>

<div class="card shadow-sm mb-4"><div class="card-header">Add</div><div class="card-body">
    <form method="POST" action="{{ route('admin.billing.store', $type) }}" class="row g-2">@csrf
        @if($type === 'credit-packs')
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Name, e.g. 10 contacts" required></div>
            <div class="col-md-2"><input type="number" name="credits" class="form-control form-control-sm" placeholder="Credits" required></div>
            <div class="col-md-2"><input type="number" name="price" class="form-control form-control-sm" placeholder="Price ₹" required></div>
            <div class="col-md-2"><input type="number" step="0.01" name="gst_percent" class="form-control form-control-sm" value="18" required></div>
        @elseif($type === 'promotion-packages')
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
            <div class="col-md-2"><input type="number" name="duration_days" class="form-control form-control-sm" placeholder="Days" required></div>
            <div class="col-md-2"><input type="number" name="price" class="form-control form-control-sm" placeholder="Price ₹" required></div>
            <div class="col-md-1"><input type="number" step="0.01" name="gst_percent" class="form-control form-control-sm" value="18" required></div>
            <div class="col-md-2 align-self-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="show_on_homepage" value="1" id="hp"><label class="form-check-label small" for="hp">Homepage</label></div></div>
        @else
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="CODE" required></div>
            <div class="col-md-2"><select name="type" class="form-select form-select-sm"><option value="percent">Percent</option><option value="fixed">Fixed ₹</option></select></div>
            <div class="col-md-1"><input type="number" name="value" class="form-control form-control-sm" placeholder="Value" required></div>
            <div class="col-md-2"><input type="number" name="max_discount" class="form-control form-control-sm" placeholder="Max discount ₹"></div>
            <div class="col-md-2"><input type="number" name="min_amount" class="form-control form-control-sm" placeholder="Min purchase ₹"></div>
            <div class="col-md-2"><select name="applies_to" class="form-select form-select-sm">@foreach(['all' => 'Everything', 'subscription' => 'Plans', 'credit_pack' => 'Credit packs', 'promotion' => 'Promotions'] as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="number" name="usage_limit" class="form-control form-control-sm" placeholder="Total uses"></div>
            <div class="col-md-1"><input type="number" name="per_user_limit" class="form-control form-control-sm" value="1" title="Uses per user" required></div>
            <div class="col-md-2"><input type="date" name="starts_at" class="form-control form-control-sm" title="Starts"></div>
            <div class="col-md-2"><input type="date" name="ends_at" class="form-control form-control-sm" title="Ends"></div>
        @endif
        <div class="col-md-auto"><button class="btn btn-sm btn-primary">Add</button></div>
    </form>
</div></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr>
        @if($type === 'coupons')<th>Code</th><th>Discount</th><th>Applies to</th><th>Used</th><th>Total uses</th><th>Ends</th>
        @else<th>Name</th><th>{{ $type === 'credit-packs' ? 'Credits' : 'Days' }}</th><th>Price ₹</th>@if($type === 'promotion-packages')<th>Homepage</th>@endif<th>Order</th>@endif
        <th>Active</th><th></th></tr></thead><tbody>
    @foreach($items as $item)
        <tr>
            @if($type === 'coupons')
                <td><code>{{ $item->code }}</code></td><td>{{ $item->type === 'percent' ? $item->value.'%' : '₹'.number_format($item->value) }}@if($item->max_discount) (max ₹{{ number_format($item->max_discount) }})@endif</td>
                <td>{{ $item->applies_to }}</td><td>{{ $item->redemptions_count }}</td>
                <td><input form="f{{ $item->id }}" type="number" name="usage_limit" value="{{ $item->usage_limit }}" class="form-control form-control-sm" style="width:90px"></td>
                <td><input form="f{{ $item->id }}" type="date" name="ends_at" value="{{ $item->ends_at?->format('Y-m-d') }}" class="form-control form-control-sm"></td>
            @else
                <td>{{ $item->name }}</td><td>{{ $type === 'credit-packs' ? $item->credits : $item->duration_days }}</td>
                <td><input form="f{{ $item->id }}" type="number" name="price" value="{{ $item->price }}" class="form-control form-control-sm" style="width:100px"></td>
                @if($type === 'promotion-packages')<td>{{ $item->show_on_homepage ? 'Yes' : '' }}</td>@endif
                <td><input form="f{{ $item->id }}" type="number" name="sort_order" value="{{ $item->sort_order }}" class="form-control form-control-sm" style="width:70px"></td>
            @endif
            <td><input form="f{{ $item->id }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($item->is_active)></td>
            <td class="text-end"><button form="f{{ $item->id }}" class="btn btn-sm btn-outline-primary">Save</button></td>
        </tr>
    @endforeach
    </tbody></table></div></div>
@foreach($items as $item)<form id="f{{ $item->id }}" method="POST" action="{{ route('admin.billing.update', [$type, $item->id]) }}">@csrf @method('PUT')</form>@endforeach
@endsection
