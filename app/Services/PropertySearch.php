<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PropertySearch
{
    /** Filters accepted from the query string and stored in saved searches. */
    public const FILTER_KEYS = [
        'purpose', 'city_id', 'locality_id', 'pin_code', 'q', 'category_ids', 'price_min', 'price_max', 'bhk', 'area_min', 'area_max',
        'furnishing', 'age_max', 'floor_min', 'floor_max', 'amenities', 'posted_by', 'availability', 'tenant_preference', 'verified',
    ];

    public function query(array $f, string $sort = 'recommended'): Builder
    {
        $q = Property::query()->visible()->with(['category', 'city', 'locality', 'cover', 'user:id,status,owner_verified_until,broker_verified_until']);

        $q->when($f['purpose'] ?? null, fn ($b, $v) => $b->where('purpose', $v))
            ->when($f['city_id'] ?? null, fn ($b, $v) => $b->where('city_id', $v))
            ->when($f['locality_id'] ?? null, fn ($b, $v) => $b->where('locality_id', $v))
            ->when($f['pin_code'] ?? null, fn ($b, $v) => $b->where('pin_code', $v))
            ->when($f['category_ids'] ?? null, fn ($b, $v) => $b->whereIn('category_id', (array) $v))
            ->when($f['price_min'] ?? null, fn ($b, $v) => $b->where('list_price', '>=', (int) $v))
            ->when($f['price_max'] ?? null, fn ($b, $v) => $b->where('list_price', '<=', (int) $v))
            ->when($f['furnishing'] ?? null, fn ($b, $v) => $b->whereIn('furnishing', (array) $v))
            ->when(isset($f['age_max']) && $f['age_max'] !== '', fn ($b) => $b->where('property_age', '<=', (int) $f['age_max']))
            ->when($f['floor_min'] ?? null, fn ($b, $v) => $b->where('floor_number', '>=', (int) $v))
            ->when($f['floor_max'] ?? null, fn ($b, $v) => $b->where('floor_number', '<=', (int) $v))
            ->when($f['posted_by'] ?? null, fn ($b, $v) => $b->where('posted_by', $v))
            ->when($f['availability'] ?? null, fn ($b, $v) => $b->where('availability', $v))
            ->when($f['tenant_preference'] ?? null, fn ($b, $v) => $b->whereIn('tenant_preference', [$v, 'any']));

        // "Verified": documents reviewed for the property, or the poster is verified for the role they posted as.
        if (! empty($f['verified'])) {
            $now = now()->toDateTimeString();
            $q->where(function ($w) use ($now) {
                $w->where('properties.documents_reviewed_until', '>', $now)
                    ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('users')->whereColumn('users.id', 'properties.user_id')
                        ->whereRaw("((properties.posted_by = 'owner' and users.owner_verified_until > ?) or (properties.posted_by = 'broker' and users.broker_verified_until > ?))", [$now, $now]));
            });
        }

        if (! empty($f['bhk'])) {
            $values = array_map('intval', (array) $f['bhk']);
            $q->where(function ($w) use ($values) {
                $exact = array_filter($values, fn ($v) => $v < 5);
                $w->whereIn('bhk', $exact);

                if (in_array(5, $values, true)) {
                    $w->orWhere('bhk', '>=', 5); // "5+"
                }
            });
        }

        if (! empty($f['area_min'])) {
            $q->whereRaw('coalesce(carpet_area, built_up_area, super_built_up_area) >= ?', [(int) $f['area_min']]);
        }

        if (! empty($f['area_max'])) {
            $q->whereRaw('coalesce(carpet_area, built_up_area, super_built_up_area) <= ?', [(int) $f['area_max']]);
        }

        foreach ((array) ($f['amenities'] ?? []) as $amenityId) {
            $q->whereHas('amenities', fn ($a) => $a->where('amenities.id', (int) $amenityId));
        }

        if (! empty($f['q'])) {
            $term = '%'.addcslashes(trim($f['q']), '%_\\').'%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('sub_locality', 'like', $term)->orWhere('pin_code', trim($f['q'])));
        }

        return $this->sort($q, $sort);
    }

    public function paginate(array $filters, string $sort = 'recommended'): LengthAwarePaginator
    {
        return $this->query($filters, $sort)->paginate(config('property.page_size'))->withQueryString();
    }

    /** Similar listings for the detail page: same city, purpose and category, comparable price. */
    public function similar(Property $property, int $limit = 4)
    {
        return Property::query()->visible()->with(['category', 'city', 'locality', 'cover'])
            ->where('id', '!=', $property->id)
            ->where('purpose', $property->purpose)->where('city_id', $property->city_id)->where('category_id', $property->category_id)
            ->when($property->list_price, fn ($b, $price) => $b->whereBetween('list_price', [(int) ($price * 0.7), (int) ($price * 1.3)]))
            ->orderByRaw('locality_id = ? desc', [$property->locality_id])->orderByDesc('published_at')
            ->limit($limit)->get();
    }

    private function sort(Builder $q, string $sort): Builder
    {
        match ($sort) {
            'latest' => $q->orderByDesc('published_at'),
            'price_asc' => $q->orderByRaw('list_price is null')->orderBy('list_price'),
            'price_desc' => $q->orderByDesc('list_price'),
            'most_viewed' => $q->orderByDesc('views_count')->orderByDesc('published_at'),
            'updated' => $q->orderByDesc('updated_at'),
            // Paid featured listings first, then the poster's search_priority, then newest.
            default => $q->orderByRaw('(featured_until is not null and featured_until > ?) desc', [now()->toDateTimeString()])->orderByDesc('priority')->orderByDesc('published_at'),
        };

        return $q->orderByDesc('properties.id');
    }
}
