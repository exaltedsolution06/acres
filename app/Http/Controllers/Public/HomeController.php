<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'latest' => Property::visible()->with(['category', 'city', 'locality', 'cover'])->orderByDesc('priority')->orderByDesc('published_at')->limit(8)->get(),
            'featured' => Property::visible()->with(['category', 'city', 'locality', 'cover'])->where('homepage_until', '>', now())->inRandomOrder()->limit(4)->get(),
            'cities' => Location::active()->ofType('city')->orderBy('name')->get(),
            'categories' => PropertyCategory::active()->orderBy('sort_order')->get(),
            'seo' => [
                'title' => config('app.name').' - Buy, rent and lease property',
                'description' => 'Search verified property listings from owners and brokers. Buy, rent or lease apartments, houses, plots and commercial space.',
                'canonical' => url('/'),
            ],
        ]);
    }
}
