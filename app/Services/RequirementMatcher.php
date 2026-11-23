<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Requirement;
use Illuminate\Support\Facades\DB;

/** Matches posted requirements with live listings, in both directions. */
class RequirementMatcher
{
    public function __construct(private Notifier $notifier)
    {
    }

    /** New requirement: find existing live listings. Returns the number of new matches. */
    public function matchRequirement(Requirement $r): int
    {
        $ids = Property::query()->visible()
            ->where('purpose', $r->purpose)->where('city_id', $r->city_id)->where('properties.user_id', '!=', $r->user_id)
            ->when($r->locality_id, fn ($q, $v) => $q->where('locality_id', $v))
            ->when($r->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($r->bhk, fn ($q, $v) => $q->where('bhk', $v))
            ->when($r->furnishing, fn ($q, $v) => $q->where('furnishing', $v))
            ->when($r->budget_min, fn ($q, $v) => $q->where('list_price', '>=', $v))
            ->when($r->budget_max, fn ($q, $v) => $q->where('list_price', '<=', $v))
            ->orderByDesc('published_at')->limit(50)->pluck('properties.id');

        $new = 0;
        foreach ($ids as $id) {
            $new += $this->record($r->id, $id);
        }

        if ($new > 0) {
            $this->notifier->send($r->user, 'requirement.match', "{$new} listing(s) match your requirement",
                "We found {$new} existing listing(s) matching \"{$r->title}\".", route('requirements.show', $r));
        }

        return $new;
    }

    /** A listing just went live: find requirements it satisfies. */
    public function matchProperty(Property $p): int
    {
        $isNull = fn (string $column, $value) => fn ($w) => $w->whereNull($column)->orWhere($column, $value);

        $requirements = Requirement::query()->open()
            ->where('purpose', $p->purpose)->where('city_id', $p->city_id)->where('user_id', '!=', $p->user_id)
            ->where($isNull('locality_id', $p->locality_id))
            ->where($isNull('category_id', $p->category_id))
            ->where($isNull('bhk', $p->bhk))
            ->where($isNull('furnishing', $p->furnishing))
            ->where(fn ($w) => $w->whereNull('budget_min')->orWhere('budget_min', '<=', $p->list_price ?? 0))
            ->where(fn ($w) => $w->whereNull('budget_max')->orWhere('budget_max', '>=', $p->list_price ?? PHP_INT_MAX))
            ->with('user')->get();

        $new = 0;
        foreach ($requirements as $r) {
            if ($this->record($r->id, $p->id)) {
                $new++;
                $this->notifier->send($r->user, 'requirement.match', 'A new listing matches your requirement',
                    "\"{$p->title}\" matches \"{$r->title}\".", route('requirements.show', $r));
            }
        }

        return $new;
    }

    private function record(int $requirementId, int $propertyId): int
    {
        return DB::table('requirement_matches')->insertOrIgnore([
            'requirement_id' => $requirementId, 'property_id' => $propertyId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
