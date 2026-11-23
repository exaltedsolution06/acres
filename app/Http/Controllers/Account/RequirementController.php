<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\Requirement;
use App\Models\RequirementResponse;
use App\Services\ContactAccessService;
use App\Services\PermissionService;
use App\Services\RequirementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequirementController extends Controller
{
    public function __construct(private RequirementService $requirements, private PermissionService $permissions)
    {
    }

    // ---------- tenants and buyers ----------

    public function index(Request $request)
    {
        return view('requirements.index', [
            'requirements' => Requirement::where('user_id', $request->user()->id)->with(['city', 'locality', 'category'])->withCount(['matches', 'responses'])->latest('id')->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        $roles = $request->user()->roles()->whereIn('slug', ['tenant', 'buyer'])->pluck('slug');
        abort_if($roles->isEmpty(), 403, 'Add the Tenant or Buyer role to post a requirement.');

        return view('requirements.form', [
            'roles' => $roles,
            'categories' => PropertyCategory::active()->orderBy('sort_order')->get(),
            'cities' => Location::active()->ofType('city')->orderBy('name')->get(),
            'localities' => Location::active()->ofType('locality')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $role = $request->input('role');
        $purposes = $role === 'buyer' ? ['sell'] : ['rent', 'lease', 'pg'];

        $data = $request->validate([
            'role' => ['required', Rule::in(['tenant', 'buyer'])],
            'purpose' => ['required', Rule::in($purposes)],
            'title' => ['required', 'string', 'min:10', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', Rule::exists('property_categories', 'id')->where('is_active', true)],
            'city_id' => ['required', Rule::exists('locations', 'id')->where('type', 'city')->where('is_active', true)],
            'locality_id' => ['nullable', Rule::exists('locations', 'id')->where('type', 'locality')->where('parent_id', $request->input('city_id'))],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:1', 'gte:budget_min'],
            'bhk' => ['nullable', 'integer', 'between:1,10'],
            'furnishing' => ['nullable', Rule::in(array_keys(config('property.furnishing')))],
        ]);

        if (! $request->user()->hasRole($data['role'])) {
            throw ValidationException::withMessages(['role' => 'You do not have this role.']);
        }

        $requirement = $this->requirements->create($request->user(), $data);

        return redirect()->route('requirements.show', $requirement)->with('status', 'Requirement posted.');
    }

    public function close(Request $request, Requirement $requirement)
    {
        abort_unless($requirement->user_id === $request->user()->id, 403);
        $this->requirements->close($requirement);

        return back()->with('status', 'Requirement closed.');
    }

    // ---------- viewing ----------

    public function show(Request $request, Requirement $requirement)
    {
        $user = $request->user();
        $isOwner = $requirement->user_id === $user->id;
        $isPoster = $user->roles()->whereIn('slug', ['owner', 'broker'])->exists();

        abort_unless($isOwner || ($isPoster && $requirement->isOpen()), 403);

        $requirement->load(['city', 'locality', 'category', 'user']);

        return view('requirements.show', [
            'requirement' => $requirement,
            'isOwner' => $isOwner,
            'matches' => $isOwner ? $requirement->matches()->with(['property' => fn ($q) => $q->with(['category', 'city', 'locality', 'cover'])])->latest('id')->get()->pluck('property')->filter() : collect(),
            'responses' => $isOwner ? $requirement->responses()->with(['user', 'property'])->get() : collect(),
            'myProperties' => $isOwner ? collect() : Property::visible()->where('properties.user_id', $user->id)->where('purpose', $requirement->purpose)->get(),
            'responded' => ! $isOwner && $this->requirements->hasResponded($user, $requirement),
            'contact' => session('contact'),
        ]);
    }

    // ---------- owners and brokers ----------

    /** Requirements open to responses. Needs the plan's requirement_posting feature. */
    public function browse(Request $request)
    {
        $user = $request->user();
        abort_unless($user->roles()->whereIn('slug', ['owner', 'broker'])->exists(), 403, 'Only owners and brokers can browse requirements.');

        $role = $user->roles()->whereIn('slug', ['owner', 'broker'])->where('roles.id', $user->active_role_id)->value('slug')
            ?? $user->roles()->whereIn('slug', ['owner', 'broker'])->value('slug');
        $this->permissions->assert($user, 'requirement_posting', $role);

        return view('requirements.browse', [
            'requirements' => Requirement::open()->where('user_id', '!=', $user->id)->with(['city', 'locality', 'category', 'user'])
                ->when($request->purpose, fn ($q, $v) => $q->where('purpose', $v))
                ->when($request->city_id, fn ($q, $v) => $q->where('city_id', $v))
                ->latest('id')->paginate(15)->withQueryString(),
            'cities' => Location::active()->ofType('city')->orderBy('name')->get(),
        ]);
    }

    public function respond(Request $request, Requirement $requirement)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000'], 'property_id' => ['nullable', 'integer']]);
        $property = ! empty($data['property_id']) ? Property::where('id', $data['property_id'])->first() : null;

        if (! empty($data['property_id']) && ! $property) {
            throw ValidationException::withMessages(['property_id' => 'Choose one of your live listings.']);
        }

        $this->requirements->respond($request->user(), $requirement, $property, $data['message']);

        return back()->with('status', 'Response sent. The requirement owner has been notified.');
    }

    /** A responder reveals the requirement owner's number (their plan's contact rules apply). */
    public function contactOwner(Request $request, Requirement $requirement, ContactAccessService $contacts)
    {
        abort_unless($requirement->isOpen(), 404);
        $result = $contacts->reveal($request->user(), $requirement->user, null, $this->requirements->hasResponded($request->user(), $requirement), $request->ip());

        return redirect()->route('requirements.show', $requirement)->with('contact', $result);
    }

    /** The requirement owner reveals a responder's number. */
    public function contactResponder(Request $request, Requirement $requirement, RequirementResponse $response, ContactAccessService $contacts)
    {
        abort_unless($requirement->user_id === $request->user()->id && $response->requirement_id === $requirement->id, 403);
        $result = $contacts->reveal($request->user(), $response->user, $response->property_id, true, $request->ip());

        return redirect()->route('requirements.show', $requirement)->with('contact', $result + ['for' => $response->id]);
    }
}
