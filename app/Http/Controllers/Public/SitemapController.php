<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', 3600, fn () => view('public.sitemap', ['urls' => $this->urls()])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** @return array<int, array{loc: string, lastmod: ?string}> */
    private function urls(): array
    {
        $urls = [['loc' => url('/'), 'lastmod' => null]];
        $verbs = config('property.url_verbs');

        // Landing pages only for areas that have enough published listings (no thin pages).
        $areaCounts = Property::visible()->selectRaw('purpose, city_id, locality_id, count(*) as total')->groupBy('purpose', 'city_id', 'locality_id')->get();
        $locations = Location::active()->whereIn('type', ['city', 'locality'])->get()->keyBy('id');
        $byArea = [];

        foreach ($areaCounts as $row) {
            foreach ([$row->city_id, $row->locality_id] as $locationId) {
                $byArea[$row->purpose][$locationId] = ($byArea[$row->purpose][$locationId] ?? 0) + $row->total;
            }
        }

        foreach ($byArea as $purpose => $areas) {
            $verb = array_search($purpose, $verbs, true);

            foreach ($areas as $locationId => $total) {
                if ($verb && isset($locations[$locationId]) && $total >= config('property.min_listings_area_landing')) {
                    $urls[] = ['loc' => route('landing', [$verb, $locations[$locationId]->seo_slug]), 'lastmod' => null];
                }
            }
        }

        $categories = PropertyCategory::active()->get()->keyBy('id');
        $catCounts = Property::visible()->selectRaw('purpose, city_id, category_id, count(*) as total')->groupBy('purpose', 'city_id', 'category_id')->get();

        foreach ($catCounts as $row) {
            $verb = array_search($row->purpose, $verbs, true);

            if ($verb && $row->total >= config('property.min_listings_category_landing') && isset($locations[$row->city_id], $categories[$row->category_id])) {
                $urls[] = ['loc' => route('landing.category', [$verb, $locations[$row->city_id]->seo_slug, $categories[$row->category_id]->slug]), 'lastmod' => null];
            }
        }

        Property::visible()->select('slug', 'updated_at')->orderByDesc('updated_at')->limit(40000)->get()->each(function ($p) use (&$urls) {
            $urls[] = ['loc' => route('property.show', $p->slug), 'lastmod' => $p->updated_at->toAtomString()];
        });

        return $urls;
    }
}
