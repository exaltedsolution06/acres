<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyImage;
use App\Services\PropertyMedia;
use App\Services\PropertyService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function __construct(private PropertyService $properties, private PropertyMedia $media)
    {
    }

    public function index(Request $request)
    {
        return view('listings.index', [
            'properties' => Property::where('user_id', $request->user()->id)->with(['category', 'locality', 'city', 'cover'])->latest('id')->paginate(15),
            'canPost' => $this->postingRoles($request)->isNotEmpty(),
        ]);
    }

    public function create(Request $request)
    {
        $roles = $this->postingRoles($request);

        return view('listings.form', $this->formData(new Property(['posted_by' => $roles->first(), 'purpose' => 'sell', 'availability' => 'ready_to_move']), $request, $roles));
    }

    public function store(Request $request)
    {
        $this->assertCanPost($request);
        [$data, $amenities] = $this->validated($request);

        $property = $this->properties->create($request->user(), $data, $amenities);

        return redirect()->route('listings.edit', $property)->with('status', 'Draft saved. Add photos, then submit it for approval.');
    }

    public function edit(Request $request, Property $property)
    {
        Gate::authorize('manage', $property);

        return view('listings.form', $this->formData($property->load('amenities', 'images', 'video', 'histories'), $request, $this->postingRoles($request)));
    }

    public function update(Request $request, Property $property)
    {
        Gate::authorize('manage', $property);
        [$data, $amenities] = $this->validated($request, $property);

        $this->properties->update($property, $data, $amenities);

        return back()->with('status', 'Listing saved.');
    }

    public function submit(Property $property)
    {
        Gate::authorize('manage', $property);
        $this->properties->submit($property);

        return redirect()->route('listings.index')->with('status', 'Submitted. We will review it shortly.');
    }

    public function pause(Property $property)
    {
        return $this->act($property, fn () => $this->properties->pause($property), 'Listing paused.');
    }

    public function resume(Property $property)
    {
        return $this->act($property, fn () => $this->properties->resume($property), 'Listing is live again.');
    }

    public function sold(Property $property)
    {
        return $this->act($property, fn () => $this->properties->markSold($property), 'Marked as sold.');
    }

    public function rented(Property $property)
    {
        return $this->act($property, fn () => $this->properties->markRented($property), 'Marked as rented.');
    }

    public function renew(Property $property)
    {
        return $this->act($property, fn () => $this->properties->renew($property), 'Listing renewed.');
    }

    public function archive(Property $property)
    {
        return $this->act($property, fn () => $this->properties->archive($property), 'Listing archived.');
    }

    public function destroy(Property $property)
    {
        Gate::authorize('manage', $property);

        if ($property->status !== Property::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only drafts can be deleted. Archive other listings instead.']);
        }

        $property->delete();

        return redirect()->route('listings.index')->with('status', 'Draft deleted.');
    }

    // ---------- media ----------

    public function addImages(Request $request, Property $property)
    {
        Gate::authorize('manage', $property);
        abort_unless($this->properties->canEdit($property), 403);

        $request->validate([
            'type' => ['required', 'in:photo,floor_plan'],
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('property.photo_max_kb'), 'dimensions:max_width=4600,max_height=4600'],
        ]);

        foreach ($request->file('images') as $file) {
            $this->media->addImage($property, $file, $request->input('type')); // stops with a limit error when the plan's photo_limit is reached
        }

        return back()->with('status', 'Uploaded.');
    }

    public function removeImage(Property $property, PropertyImage $image)
    {
        Gate::authorize('manage', $property);
        abort_unless($image->property_id === $property->id, 404);

        $this->media->removeImage($image);

        return back()->with('status', 'Image removed.');
    }

    public function coverImage(Property $property, PropertyImage $image)
    {
        Gate::authorize('manage', $property);
        abort_unless($image->property_id === $property->id, 404);

        $this->media->setCover($image);

        return back()->with('status', 'Cover photo updated.');
    }

    public function setVideo(Request $request, Property $property)
    {
        Gate::authorize('manage', $property);
        abort_unless($this->properties->canEdit($property), 403);

        $request->validate(['video' => ['required', 'file', 'mimes:mp4,webm', 'mimetypes:video/mp4,video/webm', 'max:'.config('property.video_max_kb')]]);

        $this->media->setVideo($property, $request->file('video'));

        return back()->with('status', 'Video uploaded.');
    }

    public function removeVideo(Property $property)
    {
        Gate::authorize('manage', $property);
        $this->media->removeVideo($property);

        return back()->with('status', 'Video removed.');
    }

    /** Self-service: add a role to your own account (the account keeps one shared usage allowance). */
    public function addRole(Request $request, RoleService $roles)
    {
        $data = $request->validate(['role' => ['required', 'in:'.implode(',', config('marketplace.roles'))]]);
        $roles->assign($request->user(), $data['role']);

        return back()->with('status', ucfirst($data['role']).' role added to your account.');
    }

    // ---------- helpers ----------

    private function act(Property $property, \Closure $action, string $message)
    {
        Gate::authorize('manage', $property);

        try {
            $action();
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }

    private function postingRoles(Request $request)
    {
        return $request->user()->roles()->whereIn('slug', ['owner', 'broker'])->pluck('slug');
    }

    private function assertCanPost(Request $request): void
    {
        abort_if($this->postingRoles($request)->isEmpty(), 403, 'Add the Owner or Broker role to post properties.');
    }

    private function formData(Property $property, Request $request, $roles): array
    {
        return [
            'property' => $property,
            'postingRoles' => $roles,
            'categories' => PropertyCategory::active()->orderBy('sort_order')->get(),
            'cities' => Location::active()->ofType('city')->orderBy('name')->get(),
            'localities' => Location::active()->ofType('locality')->orderBy('name')->get(),
            'amenities' => Amenity::where('is_active', true)->orderBy('name')->get(),
            'selectedAmenities' => $property->exists ? $property->amenities->pluck('id')->all() : [],
            'canEdit' => ! $property->exists || $this->properties->canEdit($property),
            'photoLimit' => $property->exists ? app(\App\Services\PermissionService::class)->limit($request->user(), 'photo_limit', $property->posted_by) : null,
            'canVideo' => $property->exists && app(\App\Services\PermissionService::class)->can($request->user(), 'video_upload', $property->posted_by),
        ];
    }

    /** @return array{0: array, 1: array} property data and amenity ids */
    private function validated(Request $request, ?Property $existing = null): array
    {
        $purposes = array_keys(config('property.purposes'));
        $purpose = $request->input('purpose');
        $rental = in_array($purpose, ['rent', 'lease', 'pg'], true);
        $category = PropertyCategory::find($request->input('category_id'));
        $keys = fn (string $name) => Rule::in(array_keys(config("property.{$name}")));

        $data = $request->validate([
            'posted_by' => ['required', Rule::in(['owner', 'broker'])],
            'category_id' => ['required', Rule::exists('property_categories', 'id')->where('is_active', true)],
            'purpose' => ['required', Rule::in($purposes)],
            'title' => ['required', 'string', 'min:10', 'max:150'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'ownership_type' => ['nullable', $keys('ownership')],
            'availability' => ['required', $keys('availability')],
            'available_from' => ['nullable', 'date'],
            'tenant_preference' => ['nullable', $keys('tenant_preference')],
            'price' => [$purpose === 'sell' ? 'required' : 'nullable', 'integer', 'min:1', 'max:99999999999'],
            'monthly_rent' => [$rental ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000000'],
            'security_deposit' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
            'maintenance_charges' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'price_negotiable' => ['boolean'],
            'bhk' => [$category?->has_bhk ? 'required' : 'nullable', 'integer', 'between:1,10'],
            'bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'bathrooms' => ['nullable', 'integer', 'between:0,20'],
            'balconies' => ['nullable', 'integer', 'between:0,20'],
            'carpet_area' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'built_up_area' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'super_built_up_area' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'furnishing' => ['nullable', $keys('furnishing')],
            'floor_number' => ['nullable', 'integer', 'between:-2,200'],
            'total_floors' => ['nullable', 'integer', 'between:1,200'],
            'property_age' => ['nullable', 'integer', 'between:0,100'],
            'facing' => ['nullable', $keys('facing')],
            'parking' => ['nullable', $keys('parking')],
            'water_supply' => ['nullable', 'string', 'max:30'],
            'power_backup' => ['nullable', $keys('power_backup')],
            'city_id' => ['required', Rule::exists('locations', 'id')->where('type', 'city')->where('is_active', true)],
            'locality_id' => ['required', Rule::exists('locations', 'id')->where('type', 'locality')->where('is_active', true)->where('parent_id', $request->input('city_id'))],
            'sub_locality' => ['nullable', 'string', 'max:100'],
            'pin_code' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'address' => ['nullable', 'string', 'max:300'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', Rule::exists('amenities', 'id')],
        ]);

        if (! $this->postingRoles($request)->contains($data['posted_by'])) {
            throw ValidationException::withMessages(['posted_by' => 'You do not have this role. Add it to your account first.']);
        }

        if ($existing && $existing->posted_by !== $data['posted_by'] && $existing->status !== Property::DRAFT) {
            throw ValidationException::withMessages(['posted_by' => 'The poster role cannot change after submission.']);
        }

        $amenities = $data['amenities'] ?? [];
        unset($data['amenities']);
        $data['price_negotiable'] = $request->boolean('price_negotiable');

        // Prices belong to the purpose: clear the field that does not apply.
        if ($purpose === 'sell') {
            $data['monthly_rent'] = null;
        } else {
            $data['price'] = null;
        }

        return [$data, $amenities];
    }
}
