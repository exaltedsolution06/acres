<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\LimitExceededException;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Services\ContactAccessService;
use App\Services\EnquiryService;
use App\Services\PropertySearch;
use App\Services\UsageTrackingService;
use App\Support\TrustBadges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    public function show(Request $request, string $slug, PropertySearch $search, UsageTrackingService $usage)
    {
        $publicId = substr($slug, -8);
        abort_unless(preg_match('/^[a-z0-9]{8}$/', $publicId) === 1, 404);

        $property = Property::with(['category', 'city.parent', 'locality', 'images', 'amenities', 'video', 'user.profile'])
            ->where('public_id', $publicId)->firstOrFail();

        $viewer = $request->user();
        $isOwner = $viewer && $viewer->id === $property->user_id;
        $isAdminPreview = Auth::guard('admin')->check();

        if (! $property->isVisible() && ! $property->isGone() && ! $isOwner && ! $isAdminPreview) {
            abort(404);
        }

        // Old slugs (title edits) redirect permanently to the canonical URL.
        if ($property->slug !== $slug) {
            return redirect()->route('property.show', $property->slug, 301);
        }

        $gone = $property->isGone();
        $limitReached = false;

        if (! $gone && ! $isOwner && ! $isAdminPreview) {
            if ($viewer) {
                // Daily allowance is per account; revisiting the same property on the same day is free.
                try {
                    $usage->consume($viewer, 'daily_property_views', null, 'property', $property->id, true);
                } catch (LimitExceededException) {
                    $limitReached = true;
                } catch (FeatureNotAvailableException) {
                    // A user with no active role has no plan: treat like a guest.
                }
            } elseif (! config('property.guest_property_views')) {
                return redirect()->guest(route('login'));
            }

            $this->countView($request, $property);
        }

        $this->rememberRecent($request, $property);

        $canonical = route('property.show', $property->slug);
        $seo = [
            'title' => $property->seoTitle().' | '.config('app.name'),
            'description' => \Illuminate\Support\Str::limit(strip_tags($property->description), 155),
            'canonical' => $canonical,
            'robots' => ($gone || ! $property->isVisible()) ? 'noindex,follow' : null,
            'og_image' => $property->images->firstWhere('type', 'photo')?->url(),
        ];

        $jsonLd = $this->jsonLd($property, $canonical);

        // Over the daily allowance: nothing about the listing may leak through meta tags or structured data.
        if ($limitReached) {
            $seo = [
                'title' => 'Daily view limit reached | '.config('app.name'),
                'description' => 'You have reached your daily property view limit.',
                'canonical' => $canonical,
                'robots' => 'noindex,nofollow',
            ];
            $jsonLd = null;
        }

        return view('public.property', [
            'property' => $property,
            'gone' => $gone,
            'limitReached' => $limitReached,
            'similar' => $search->similar($property),
            'seo' => $seo,
            'jsonLd' => $jsonLd,
            'crumbs' => [
                ['Home', url('/')],
                [$property->city->name, route('landing', [$property->urlVerb(), $property->city->seo_slug])],
                [$property->locality->name, route('landing', [$property->urlVerb(), $property->locality->seo_slug])],
                [$property->typeLabel(), null],
            ],
            'contact' => session('contact'),
            'enquiry' => $viewer ? Enquiry::where('property_id', $property->id)->where('from_user_id', $viewer->id)->first() : null,
            'isSaved' => $viewer ? SavedProperty::where('property_id', $property->id)->where('user_id', $viewer->id)->exists() : false,
            'isOwner' => $isOwner,
            'posterBadges' => $limitReached ? [] : TrustBadges::forPoster($property->user, $property->posted_by),
            'propertyBadges' => $limitReached ? [] : TrustBadges::forProperty($property),
        ]);
    }

    /** Server-validated contact reveal. The number never appears in page HTML before this succeeds. */
    public function contact(Request $request, Property $property, ContactAccessService $contacts, EnquiryService $enquiries)
    {
        abort_unless($property->isVisible(), 404);

        try {
            $result = $contacts->reveal($request->user(), $property->user, $property->id, $enquiries->hasShownInterest($request->user(), $property), $request->ip());
        } catch (FeatureNotAvailableException | LimitExceededException $e) {
            return redirect()->route('property.show', $property->slug)->withErrors(['contact' => $e->getMessage()]);
        }

        return redirect()->route('property.show', $property->slug)->with('contact', $result);
    }

    private function countView(Request $request, Property $property): void
    {
        $seen = (array) $request->session()->get('viewed_properties', []);

        if (! in_array($property->id, $seen, true)) {
            Property::whereKey($property->id)->increment('views_count');
            $request->session()->put('viewed_properties', array_slice(array_merge([$property->id], $seen), 0, 100));
        }
    }

    private function rememberRecent(Request $request, Property $property): void
    {
        $recent = array_values(array_unique(array_merge([$property->id], (array) $request->session()->get('recent_properties', []))));
        $request->session()->put('recent_properties', array_slice($recent, 0, 10));
    }

    private function jsonLd(Property $property, string $canonical): array
    {
        $images = $property->images->where('type', 'photo')->take(5)->map(fn ($i) => $i->url())->values()->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $property->title,
            'url' => $canonical,
            'description' => \Illuminate\Support\Str::limit(strip_tags($property->description), 300),
            'datePosted' => $property->published_at?->toIso8601String(),
            'image' => $images ?: null,
            'offers' => $property->list_price ? [
                '@type' => 'Offer', 'price' => $property->list_price, 'priceCurrency' => 'INR',
                'availability' => $property->isVisible() ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            ] : null,
            'contentLocation' => [
                '@type' => 'Place',
                'address' => array_filter([
                    '@type' => 'PostalAddress', 'addressLocality' => $property->locality->name,
                    'addressRegion' => $property->city->parent?->name, 'postalCode' => $property->pin_code, 'addressCountry' => 'IN',
                ]),
            ],
        ]);
    }
}
