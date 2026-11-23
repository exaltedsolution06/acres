@extends('layouts.admin')
@section('title', $feature->exists ? 'Edit feature' : 'New feature')
@section('content')
<h1 class="h4 mb-3">{{ $feature->exists ? 'Edit '.$feature->name : 'New feature' }}</h1>
<form method="POST" action="{{ $feature->exists ? route('admin.features.update', $feature) : route('admin.features.store') }}" class="card shadow-sm" style="max-width:640px"><div class="card-body row g-3">
    @csrf @if($feature->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label">Key</label>
        <input name="key" class="form-control" value="{{ old('key', $feature->key) }}" placeholder="e.g. whatsapp_enquiry" @readonly($feature->exists) required>
        <div class="form-text">Lowercase letters, numbers, underscores. Cannot be changed later.</div></div>
    <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $feature->name) }}" required></div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control">{{ old('description', $feature->description) }}</textarea></div>
    <div class="col-md-4"><label class="form-label">Type</label>
        @if($feature->exists)<input class="form-control" value="{{ $feature->type }}" disabled>
        @else<select name="type" class="form-select">@foreach(['boolean', 'limit', 'enum'] as $t)<option value="{{ $t }}" @selected(old('type', $feature->type) === $t)>{{ $t }}</option>@endforeach</select>@endif</div>
    <div class="col-md-4"><label class="form-label">Default value</label><input name="default_value" class="form-control" value="{{ old('default_value', $feature->default_value) }}"><div class="form-text">boolean: 1/0 · limit: number</div></div>
    <div class="col-md-4"><label class="form-label">Enum options</label><input name="options" class="form-control" value="{{ old('options', implode(', ', $feature->options ?? [])) }}"><div class="form-text">Comma separated</div></div>
    <div class="col-12">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_daily" value="1" id="is_daily" @checked(old('is_daily', $feature->is_daily))><label class="form-check-label" for="is_daily">Daily allowance (limit type only)</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $feature->is_active))><label class="form-check-label" for="is_active">Active</label></div>
    </div>
    <div class="col-12"><button class="btn btn-primary">Save</button> <a class="btn btn-link" href="{{ route('admin.features.index') }}">Cancel</a></div>
</div></form>
@endsection
