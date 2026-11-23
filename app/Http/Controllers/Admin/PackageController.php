<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Role;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Services\PackageManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    public function __construct(private PackageManager $manager, private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $packages = Package::with('role')
            ->withCount(['featureValues'])
            ->when($request->role, fn ($q, $role) => $q->where('role_id', $role))
            ->orderBy('role_id')->orderBy('display_order')->get();

        $activeCounts = Subscription::where('status', 'active')
            ->selectRaw('package_id, count(*) as total')->groupBy('package_id')->pluck('total', 'package_id');

        return view('admin.packages.index', [
            'packages' => $packages, 'roles' => Role::all(), 'activeCounts' => $activeCounts,
        ]);
    }

    public function create()
    {
        return view('admin.packages.form', $this->formData(new Package([
            'gst_percent' => 18, 'status' => 'active', 'grace_days' => 0, 'trial_days' => 0, 'display_order' => 0,
        ])));
    }

    public function store(Request $request)
    {
        $package = $this->manager->save(new Package, $this->validated($request), $request->input('features', []));
        $this->audit->log('package.created', $package, null, $package->toArray());

        return redirect()->route('admin.packages.index')->with('success', "Package \"{$package->name}\" created.");
    }

    public function edit(Package $package)
    {
        return view('admin.packages.form', $this->formData($package));
    }

    public function update(Request $request, Package $package)
    {
        $old = $package->toArray() + ['features' => $this->snapshot($package)];
        $this->manager->save($package, $this->validated($request, $package), $request->input('features', []));
        $this->audit->log('package.updated', $package, $old, $package->fresh()->toArray() + ['features' => $this->snapshot($package)]);

        return redirect()->route('admin.packages.index')->with('success', 'Package updated.');
    }

    public function duplicate(Package $package)
    {
        $copy = $this->manager->duplicate($package);
        $this->audit->log('package.duplicated', $copy, ['source_id' => $package->id]);

        return redirect()->route('admin.packages.edit', $copy)->with('success', 'Package duplicated as inactive. Review and activate it.');
    }

    public function toggle(Package $package)
    {
        if ($package->is_default) {
            return back()->withErrors(['package' => 'The default package for a role must stay active. Make another package the default first.']);
        }

        $new = $package->status === 'active' ? 'inactive' : 'active';
        $package->update(['status' => $new]);
        $this->audit->log('package.status', $package, ['status' => $package->status === 'active' ? 'inactive' : 'active'], ['status' => $new]);

        return back()->with('success', "Package is now {$new}.");
    }

    public function destroy(Package $package)
    {
        if ($package->is_default) {
            return back()->withErrors(['package' => 'The default package for a role cannot be deleted.']);
        }

        if (Subscription::where('package_id', $package->id)->where('status', 'active')->exists()) {
            return back()->withErrors(['package' => 'This package has active subscribers. Deactivate it instead.']);
        }

        $this->audit->log('package.deleted', $package, $package->toArray());
        $package->delete();

        return redirect()->route('admin.packages.index')->with('success', 'Package deleted.');
    }

    private function formData(Package $package): array
    {
        return [
            'package' => $package,
            'roles' => Role::all(),
            'features' => PackageFeature::where('is_active', true)->orderBy('is_daily', 'desc')->orderBy('id')->get(),
            'values' => $package->exists ? $package->featureValues->keyBy('feature_id') : collect(),
        ];
    }

    private function snapshot(Package $package): array
    {
        return $package->featureValues()->with('feature')->get()
            ->mapWithKeys(fn ($v) => [$v->feature->key => $v->is_unlimited ? 'unlimited' : $v->value])->all();
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'gst_percent' => ['required', 'numeric', 'between:0,100'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'renewal_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'display_order' => ['required', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data += [
            'is_free' => $request->boolean('is_free'),
            'is_default' => $request->boolean('is_default'),
            'is_recommended' => $request->boolean('is_recommended'),
            'auto_renew' => $request->boolean('auto_renew'),
            'refund_eligible' => $request->boolean('refund_eligible'),
        ];
        $data['renewal_discount_percent'] ??= 0;

        if (! $data['is_free'] && empty($data['validity_days'])) {
            throw ValidationException::withMessages(['validity_days' => 'Validity is required for paid packages.']);
        }

        if ($data['is_default'] && ! $data['is_free']) {
            throw ValidationException::withMessages(['is_default' => 'Only a free package can be the default fallback.']);
        }

        if ($package && (int) $data['role_id'] !== $package->role_id && Subscription::where('package_id', $package->id)->exists()) {
            throw ValidationException::withMessages(['role_id' => 'The role cannot be changed once the package has subscribers.']);
        }

        return $data;
    }
}
