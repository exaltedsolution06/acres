<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageFeature;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index()
    {
        return view('admin.features.index', ['features' => PackageFeature::orderBy('is_daily', 'desc')->orderBy('id')->get()]);
    }

    public function create()
    {
        return view('admin.features.form', ['feature' => new PackageFeature(['type' => 'boolean', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'regex:/^[a-z][a-z0-9_]{2,59}$/', 'unique:package_features,key'],
        ] + $this->rules());

        $feature = PackageFeature::create($this->payload($request, $data) + ['key' => $data['key']]);
        $this->audit->log('feature.created', $feature, null, $feature->toArray());

        return redirect()->route('admin.features.index')->with('success', 'Feature added. It now appears in every package form.');
    }

    public function edit(PackageFeature $feature)
    {
        return view('admin.features.form', ['feature' => $feature]);
    }

    /** The key and type are immutable: changing them would corrupt stored package values. */
    public function update(Request $request, PackageFeature $feature)
    {
        $data = $request->validate($this->rules(false));
        $old = $feature->toArray();
        $feature->update($this->payload($request, $data, $feature));
        $this->audit->log('feature.updated', $feature, $old, $feature->fresh()->toArray());

        return redirect()->route('admin.features.index')->with('success', 'Feature updated.');
    }

    private function rules(bool $creating = true): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => $creating ? ['required', Rule::in(['boolean', 'limit', 'enum'])] : ['nullable'],
            'default_value' => ['nullable', 'string', 'max:100'],
            'options' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function payload(Request $request, array $data, ?PackageFeature $existing = null): array
    {
        $type = $existing?->type ?? $data['type'];
        $options = $type === 'enum'
            ? array_values(array_filter(array_map('trim', explode(',', (string) ($data['options'] ?? '')))))
            : null;
        $default = $data['default_value'] ?? null;

        $default = match ($type) {
            'boolean' => in_array($default, ['1', 'true', 'yes'], true) ? '1' : '0',
            'limit' => (string) max(0, (int) $default),
            default => in_array($default, $options ?? [], true) ? $default : ($options[0] ?? null),
        };

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'default_value' => $default,
            'options' => $options,
            'is_daily' => $type === 'limit' && $request->boolean('is_daily'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
