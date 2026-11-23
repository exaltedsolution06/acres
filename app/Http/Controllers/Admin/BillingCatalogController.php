<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CreditPack;
use App\Models\PromotionPackage;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Credit packs, promotion packages and coupons. Items are switched off, not deleted, because payments reference them. */
class BillingCatalogController extends Controller
{
    private const TYPES = ['credit-packs' => CreditPack::class, 'promotion-packages' => PromotionPackage::class, 'coupons' => Coupon::class];

    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(string $type)
    {
        $model = $this->model($type);

        return view('admin.billing.catalog', [
            'type' => $type,
            'items' => $type === 'coupons' ? $model::withCount('redemptions')->latest('id')->get() : $model::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->model($type);

        $item = match ($type) {
            'credit-packs' => CreditPack::create($request->validate([
                'name' => ['required', 'string', 'max:80'], 'credits' => ['required', 'integer', 'min:1', 'max:10000'],
                'price' => ['required', 'integer', 'min:1', 'max:1000000'], 'gst_percent' => ['required', 'numeric', 'between:0,100'],
            ]) + ['is_active' => true, 'sort_order' => (int) CreditPack::max('sort_order') + 1]),
            'promotion-packages' => PromotionPackage::create($request->validate([
                'name' => ['required', 'string', 'max:80'], 'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
                'price' => ['required', 'integer', 'min:1', 'max:1000000'], 'gst_percent' => ['required', 'numeric', 'between:0,100'],
            ]) + ['show_on_homepage' => $request->boolean('show_on_homepage'), 'is_active' => true, 'sort_order' => (int) PromotionPackage::max('sort_order') + 1]),
            default => $this->storeCoupon($request),
        };

        $this->audit->log("billing.{$type}.created", $item, null, $item->toArray());

        return back()->with('success', 'Added.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $item = $this->model($type)::findOrFail($id);
        $old = $item->toArray();

        $data = ['is_active' => $request->boolean('is_active')];

        if ($type === 'coupons') {
            $data += $request->validate(['ends_at' => ['nullable', 'date'], 'usage_limit' => ['nullable', 'integer', 'min:1']]);
        } else {
            $data += $request->validate(['price' => ['required', 'integer', 'min:1', 'max:1000000']]);

            if ($request->filled('sort_order')) {
                $data['sort_order'] = max(0, (int) $request->input('sort_order'));
            }
        }

        $item->update($data);
        $this->audit->log("billing.{$type}.updated", $item, $old, $item->fresh()->toArray());

        return back()->with('success', 'Saved.');
    }

    private function storeCoupon(Request $request): Coupon
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]); // codes are case-insensitive
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Za-z0-9_-]{3,40}$/', Rule::unique('coupons', 'code')],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:1000000'],
            'max_discount' => ['nullable', 'integer', 'min:1'],
            'min_amount' => ['nullable', 'integer', 'min:1'],
            'applies_to' => ['required', 'in:all,subscription,credit_pack,promotion'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return Coupon::create(['code' => strtoupper($data['code'])] + $data + ['is_active' => true]);
    }

    private function model(string $type): string
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }
}
