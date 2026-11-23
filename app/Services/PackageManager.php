<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackageFeatureValue;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageManager
{
    /**
     * Create or update a package together with its per-feature values.
     * $featureInput is the form payload: [feature_id => ['value' => ..., 'unlimited' => ...]].
     */
    public function save(Package $package, array $data, array $featureInput): Package
    {
        return DB::transaction(function () use ($package, $data, $featureInput) {
            $wasDefault = $package->exists && $package->is_default;

            $package->fill($data);

            if ($package->is_free) {
                $package->price = 0;
                $package->discounted_price = null;
            }

            // The role's fallback package can only be changed by choosing another default.
            if ($wasDefault) {
                $package->is_default = true;
                $package->status = 'active';
            }

            if (! $package->exists) {
                $package->slug = $this->uniqueSlug($package->role_id, $package->name);
            }

            $package->save();

            if ($package->is_default) {
                Package::where('role_id', $package->role_id)->where('id', '!=', $package->id)->update(['is_default' => false]);
            }

            $this->saveFeatureValues($package, $featureInput);

            return $package;
        });
    }

    public function duplicate(Package $source): Package
    {
        return DB::transaction(function () use ($source) {
            $copy = $source->replicate(['slug', 'is_default', 'status']);
            $copy->name = 'Copy of '.$source->name;
            $copy->slug = $this->uniqueSlug($source->role_id, $copy->name);
            $copy->is_default = false;
            $copy->status = 'inactive';
            $copy->save();

            foreach ($source->featureValues as $value) {
                $copy->featureValues()->create($value->only(['feature_id', 'value', 'is_unlimited']));
            }

            return $copy;
        });
    }

    private function saveFeatureValues(Package $package, array $input): void
    {
        foreach (PackageFeature::where('is_active', true)->get() as $feature) {
            $in = $input[$feature->id] ?? [];
            $unlimited = false;

            if ($feature->type === 'boolean') {
                $value = ! empty($in['value']) ? '1' : '0';
            } elseif ($feature->type === 'limit') {
                $unlimited = ! empty($in['unlimited']);
                $value = $unlimited ? null : (string) max(0, (int) ($in['value'] ?? 0));
            } else {
                $candidate = $in['value'] ?? $feature->default_value;
                $value = in_array($candidate, $feature->options ?? [], true) ? $candidate : $feature->default_value;
            }

            PackageFeatureValue::updateOrCreate(
                ['package_id' => $package->id, 'feature_id' => $feature->id],
                ['value' => $value, 'is_unlimited' => $unlimited],
            );
        }
    }

    private function uniqueSlug(int $roleId, string $name): string
    {
        $base = Str::slug(Role::where('id', $roleId)->value('slug').'-'.$name);
        $slug = $base;
        $i = 2;

        while (Package::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
