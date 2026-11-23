<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\PropertyCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Categories, amenities and locations. Items are deactivated, never deleted, because listings reference them. */
class CatalogController extends Controller
{
    private const TYPES = ['categories' => PropertyCategory::class, 'amenities' => Amenity::class, 'locations' => Location::class];

    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(string $type)
    {
        $model = $this->model($type);

        return view('admin.catalog.index', [
            'type' => $type,
            'items' => match ($type) {
                'categories' => PropertyCategory::orderBy('sort_order')->get(),
                'amenities' => Amenity::orderBy('name')->get(),
                default => Location::with('parent')->orderBy('type')->orderBy('name')->get(),
            },
            'cities' => $type === 'locations' ? Location::ofType('city')->orderBy('name')->get() : collect(),
            'states' => $type === 'locations' ? Location::ofType('state')->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->model($type);

        $item = match ($type) {
            'categories' => $this->storeCategory($request),
            'amenities' => $this->storeAmenity($request),
            default => $this->storeLocation($request),
        };

        $this->audit->log("catalog.{$type}.created", $item, null, $item->toArray());

        return back()->with('success', 'Added.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $item = $this->model($type)::findOrFail($id);
        $old = $item->toArray();

        $data = ['is_active' => $request->boolean('is_active')];

        if ($type === 'categories') {
            $data += ['has_bhk' => $request->boolean('has_bhk'), 'sort_order' => (int) $request->input('sort_order', $item->sort_order)];
        } elseif ($type === 'amenities') {
            $data['name'] = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('amenities', 'name')->ignore($item->id)]])['name'];
        } else {
            $data['pin_code'] = $request->validate(['pin_code' => ['nullable', 'regex:/^[1-9][0-9]{5}$/']])['pin_code'] ?? null;
        }

        $item->update($data);
        $this->audit->log("catalog.{$type}.updated", $item, $old, $item->fresh()->toArray());

        return back()->with('success', 'Saved.');
    }

    private function storeCategory(Request $request): PropertyCategory
    {
        $data = $request->validate([
            'group' => ['required', 'in:residential,commercial'],
            'name' => ['required', 'string', 'max:80', Rule::unique('property_categories', 'name')],
        ]);

        return PropertyCategory::create($data + [
            'slug' => $this->uniqueSlug(PropertyCategory::class, $data['name']),
            'has_bhk' => $request->boolean('has_bhk'),
            'sort_order' => (int) PropertyCategory::max('sort_order') + 1,
            'is_active' => true,
        ]);
    }

    private function storeAmenity(Request $request): Amenity
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('amenities', 'name')]]);

        return Amenity::create($data + ['slug' => $this->uniqueSlug(Amenity::class, $data['name']), 'is_active' => true]);
    }

    private function storeLocation(Request $request): Location
    {
        $type = $request->input('location_type');
        $parentType = ['city' => 'state', 'locality' => 'city'][$type] ?? null;

        $data = $request->validate([
            'location_type' => ['required', 'in:state,city,locality'],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => $parentType ? ['required', Rule::exists('locations', 'id')->where('type', $parentType)] : ['nullable'],
            'pin_code' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
        ]);

        $exists = Location::where('type', $type)->where('parent_id', $data['parent_id'] ?? null)->where('name', $data['name'])->exists();
        abort_if($exists, 422, 'That location already exists.');

        return Location::create([
            'type' => $type, 'name' => $data['name'], 'parent_id' => $parentType ? $data['parent_id'] : null,
            'pin_code' => $data['pin_code'] ?? null, 'is_active' => true,
        ]);
    }

    private function model(string $type): string
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
