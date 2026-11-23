<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\PropertyCategory;
use App\Services\PropertySearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private PropertySearch $search)
    {
    }

    /** /search: a free-form results page that search engines should not index. */
    public function search(Request $request)
    {
        $filters = $request->only(PropertySearch::FILTER_KEYS);

        return $this->render($request, $filters, null, null, null, [
            'title' => 'Search property | '.config('app.name'),
            'description' => 'Search property listings.',
            'canonical' => route('search'),
            'robots' => 'noindex,follow',
        ], [['Home', url('/')], ['Search', null]]);
    }

    /** /buy-property-in-kolkata and /rent-property-in-salt-lake-kolkata */
    public function landing(Request $request, string $verb, string $area)
    {
        $location = Location::active()->whereIn('type', ['city', 'locality'])->where('seo_slug', $area)->firstOrFail();

        return $this->landingPage($request, $verb, $location, null);
    }

    /** /buy-property-in-kolkata/apartment-flat */
    public function categoryLanding(Request $request, string $verb, string $area, string $category)
    {
        $location = Location::active()->whereIn('type', ['city', 'locality'])->where('seo_slug', $area)->firstOrFail();
        $cat = PropertyCategory::active()->where('slug', $category)->firstOrFail();

        return $this->landingPage($request, $verb, $location, $cat);
    }

    private function landingPage(Request $request, string $verb, Location $location, ?PropertyCategory $category)
    {
        $purpose = config('property.url_verbs')[$verb];
        $label = match ($purpose) { 'sell' => 'for Sale', 'rent' => 'for Rent', 'lease' => 'for Lease', default => 'PG / Co-living' };
        $place = $location->type === 'city' ? $location->name : $location->name.', '.$location->parent->name;
        $noun = $category ? $category->name : 'Properties';

        $filters = ['purpose' => $purpose, $location->type === 'city' ? 'city_id' : 'locality_id' => $location->id];

        if ($category) {
            $filters['category_ids'] = [$category->id];
        }

        // Extra filters, sorting or paging beyond page 1 are for users, not for the index.
        $refined = $request->hasAny(array_merge(PropertySearch::FILTER_KEYS, ['sort'])) && $request->except('page') !== [];
        $filters = array_merge($request->only(PropertySearch::FILTER_KEYS), $filters);

        $base = $category ? route('landing.category', [$verb, $location->seo_slug, $category->slug]) : route('landing', [$verb, $location->seo_slug]);

        $crumbs = [['Home', url('/')]];
        if ($location->type === 'locality') {
            $crumbs[] = [$location->parent->name, route('landing', [$verb, $location->parent->seo_slug])];
        }
        $crumbs[] = [$location->name, $category ? route('landing', [$verb, $location->seo_slug]) : null];
        if ($category) {
            $crumbs[] = [$category->name, null];
        }

        return $this->render($request, $filters, $location, $category, $purpose, [
            'title' => "{$noun} {$label} in {$place}",
            'description' => "Browse {$noun} {$label} in {$place}. Compare prices, photos and details from owners and brokers.",
            'canonical' => $base,
            'robots' => $refined ? 'noindex,follow' : null,
        ], $crumbs, $base);
    }

    private function render(Request $request, array $filters, ?Location $location, ?PropertyCategory $category, ?string $purpose, array $seo, array $crumbs, ?string $baseUrl = null)
    {
        $sort = array_key_exists($request->input('sort'), config('property.sort')) ? $request->input('sort') : 'recommended';
        $results = $this->search->paginate($filters, $sort);

        // No listings: keep the page for users but out of the index (thin-page protection).
        if ($results->total() === 0 && empty($seo['robots'])) {
            $seo['robots'] = 'noindex,follow';
        }

        if ($results->currentPage() > 1) {
            $seo['title'] .= ' - Page '.$results->currentPage();
            $seo['canonical'] = $baseUrl ? $baseUrl.'?page='.$results->currentPage() : $seo['canonical'];
        }

        $seo['title'] .= ' | '.config('app.name');

        return view('public.search', [
            'results' => $results,
            'filters' => $filters,
            'sort' => $sort,
            'location' => $location,
            'category' => $category,
            'purpose' => $purpose ?? ($filters['purpose'] ?? null),
            'seo' => $seo,
            'crumbs' => $crumbs,
            'cities' => Location::active()->ofType('city')->orderBy('name')->get(),
            'localities' => Location::active()->ofType('locality')->orderBy('name')->get(),
            'categories' => PropertyCategory::active()->orderBy('sort_order')->get(),
            'amenities' => Amenity::where('is_active', true)->orderBy('name')->get(),
            'formAction' => $baseUrl ?? route('search'),
            'recent' => $this->recentlyViewed($request),
        ]);
    }

    private function recentlyViewed(Request $request)
    {
        $ids = array_slice((array) $request->session()->get('recent_properties', []), 0, 6);

        if (! $ids) {
            return collect();
        }

        return \App\Models\Property::visible()->with(['category', 'city', 'locality', 'cover'])->whereIn('properties.id', $ids)->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids))->values();
    }
}
