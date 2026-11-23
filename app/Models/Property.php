<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    public const DRAFT = 'draft';
    public const PENDING = 'pending_approval';
    public const PUBLISHED = 'published';
    public const REJECTED = 'rejected';
    public const PAUSED = 'paused';
    public const SOLD = 'sold';
    public const RENTED = 'rented';
    public const EXPIRED = 'expired';
    public const ARCHIVED = 'archived';

    /** Statuses that occupy one of the poster's active_listings slots. */
    public const QUOTA_STATUSES = [self::PENDING, self::PUBLISHED];

    protected $guarded = [];

    protected $casts = [
        'price_negotiable' => 'boolean',
        'available_from' => 'date',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'featured_until' => 'datetime',
        'homepage_until' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (Property $p) {
            $p->public_id ??= static::newPublicId();

            $p->list_price = $p->purpose === 'sell' ? $p->price : $p->monthly_rent;

            $area = $p->built_up_area ?: ($p->carpet_area ?: $p->super_built_up_area);
            $p->price_per_sqft = ($p->purpose === 'sell' && $p->price && $area) ? (int) round($p->price / $area) : null;

            $p->fingerprint = $p->computeFingerprint();

            if (! $p->slug || $p->isDirty(['title', 'city_id', 'locality_id'])) {
                $p->unsetRelation('city')->unsetRelation('locality');
                $p->slug = $p->buildSlug();
            }
        });
    }

    public static function newPublicId(): string
    {
        do {
            $id = Str::lower(Str::random(8));
        } while (static::withTrashed()->where('public_id', $id)->exists());

        return $id;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    // ----- relations -----
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'city_id');
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'locality_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id');
    }

    public function cover(): HasOne
    {
        return $this->hasOne(PropertyImage::class)->where('type', 'photo')->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id');
    }

    public function video(): HasOne
    {
        return $this->hasOne(PropertyVideo::class)->latestOfMany();
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenity');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PropertyStatusHistory::class)->latest('id');
    }

    // ----- scopes -----
    /** Listings that may appear in public search and the sitemap. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('properties.status', self::PUBLISHED)
            ->where(fn ($w) => $w->whereNull('properties.expires_at')->orWhere('properties.expires_at', '>', now()))
            ->whereIn('properties.user_id', fn ($s) => $s->select('id')->from('users')->where('status', 'active')->whereNull('deleted_at'));
    }

    public function isVisible(): bool
    {
        return $this->status === self::PUBLISHED
            && (! $this->expires_at || $this->expires_at->isFuture())
            && $this->user?->status === 'active';
    }

    /** Pages for listings that existed but are gone: shown with noindex rather than a 404. */
    public function isGone(): bool
    {
        return in_array($this->status, [self::SOLD, self::RENTED, self::EXPIRED], true)
            || ($this->status === self::PUBLISHED && $this->expires_at && $this->expires_at->isPast());
    }

    // ----- helpers -----
    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    public function urlVerb(): string
    {
        return array_search($this->purpose, config('property.url_verbs'), true) ?: 'buy';
    }

    public function areaSqft(): ?int
    {
        return $this->carpet_area ?: ($this->built_up_area ?: $this->super_built_up_area);
    }

    public function displayPrice(): string
    {
        return $this->purpose === 'sell'
            ? Money::inr($this->price)
            : ($this->monthly_rent ? '₹'.number_format($this->monthly_rent).'/month' : 'Price on request');
    }

    public function typeLabel(): string
    {
        $bhk = $this->bhk ? $this->bhk.' BHK ' : '';

        return $bhk.$this->category->name;
    }

    public function seoTitle(): string
    {
        $purpose = match ($this->purpose) {
            'sell' => 'for Sale', 'rent' => 'for Rent', 'lease' => 'for Lease', default => 'PG / Co-living',
        };

        return "{$this->typeLabel()} {$purpose} in {$this->locality->name}, {$this->city->name} | {$this->displayPrice()}";
    }

    public function buildSlug(): string
    {
        $base = Str::slug(Str::limit($this->title.' '.$this->locality?->name.' '.$this->city?->name, 90, ''));
        $base = trim($base, '-') ?: 'property';

        return $base.'-'.$this->public_id;
    }

    /**
     * Same listing details at the same address produce the same fingerprint.
     * Price is excluded on purpose: re-listing at a new price is still a duplicate.
     */
    public function computeFingerprint(): string
    {
        $address = preg_replace('/[^a-z0-9]/', '', Str::lower((string) $this->address.$this->sub_locality));
        $area = $this->areaSqft() ? (int) round($this->areaSqft() / 25) : 0;

        return sha1(implode('|', [
            $this->category_id, $this->purpose, $this->locality_id, (int) $this->bhk, $area,
            (int) $this->floor_number, (int) $this->total_floors, $this->pin_code, $address,
        ]));
    }
}
