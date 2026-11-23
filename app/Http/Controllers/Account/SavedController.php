<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\LimitExceededException;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Models\SavedSearch;
use App\Services\PermissionService;
use App\Services\SavedSearchService;
use Illuminate\Http\Request;

class SavedController extends Controller
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function properties(Request $request)
    {
        return view('saved.properties', [
            'saved' => SavedProperty::where('user_id', $request->user()->id)
                ->with(['property' => fn ($q) => $q->with(['category', 'city', 'locality', 'cover'])])->latest('id')->paginate(12),
        ]);
    }

    public function save(Request $request, Property $property)
    {
        $user = $request->user();
        $limit = $this->permissions->limit($user, 'saved_properties');

        if ($limit === 0) {
            throw new FeatureNotAvailableException('saved_properties', 'Saving properties is not included in your plan.');
        }

        if (! SavedProperty::where('user_id', $user->id)->where('property_id', $property->id)->exists()) {
            if ($limit !== null && SavedProperty::where('user_id', $user->id)->count() >= $limit) {
                throw new LimitExceededException('saved_properties', $limit);
            }

            SavedProperty::create(['user_id' => $user->id, 'property_id' => $property->id]);
        }

        return back()->with('status', 'Saved.');
    }

    public function unsave(Request $request, Property $property)
    {
        SavedProperty::where('user_id', $request->user()->id)->where('property_id', $property->id)->delete();

        return back()->with('status', 'Removed from saved.');
    }

    public function searches(Request $request)
    {
        return view('saved.searches', ['searches' => SavedSearch::where('user_id', $request->user()->id)->latest('id')->get()]);
    }

    public function storeSearch(Request $request, SavedSearchService $searches)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'filters' => ['required', 'string', 'max:3000']]);
        $filters = json_decode($data['filters'], true);

        $searches->save($request->user(), $data['name'], is_array($filters) ? $filters : []);

        return redirect()->route('saved.searches')->with('status', 'Search saved. You will be told when new listings match.');
    }

    public function destroySearch(Request $request, SavedSearch $search)
    {
        abort_unless($search->user_id === $request->user()->id, 403);
        $search->delete();

        return back()->with('status', 'Saved search deleted.');
    }
}
