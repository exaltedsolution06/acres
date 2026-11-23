<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Location $l) {
            $l->slug = Str::slug($l->name);

            if ($l->type === 'city') {
                $l->seo_slug = $l->slug;
            } elseif ($l->type === 'locality') {
                $l->seo_slug = $l->slug.'-'.Location::find($l->parent_id)?->slug;
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }
}
