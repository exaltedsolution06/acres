<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Services\PropertyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyModerationController extends Controller
{
    public function __construct(private PropertyService $properties, private AuditLogger $audit, private Notifier $notifier)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', Property::PENDING);

        $properties = Property::with(['user', 'category', 'city', 'locality'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('public_id', $term)))
            ->when($request->boolean('duplicates'), fn ($q) => $q->whereNotNull('duplicate_of_id'))
            ->orderBy($status === Property::PENDING ? 'updated_at' : 'id', $status === Property::PENDING ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();

        return view('admin.properties.index', [
            'properties' => $properties,
            'status' => $status,
            'counts' => Property::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Property $property)
    {
        $property->load(['user.profile', 'category', 'city', 'locality', 'images', 'video', 'amenities', 'histories']);

        return view('admin.properties.show', [
            'property' => $property,
            'original' => $property->duplicate_of_id ? Property::with('user')->find($property->duplicate_of_id) : null,
        ]);
    }

    public function approve(Property $property)
    {
        $this->properties->publish($property, Auth::guard('admin')->id());
        $this->audit->log('property.approved', $property, null, ['expires_at' => $property->expires_at?->toDateTimeString()]);

        $this->notifier->send($property->user, 'listing.reviewed', 'Your listing is live', "\"{$property->title}\" was approved and published.", route('property.show', $property->slug));

        return redirect()->route('admin.properties.index')->with('success', 'Listing approved and published.');
    }

    /** Rejects a pending listing, or takes a published one down. The owner sees the reason and can fix and resubmit. */
    public function reject(Request $request, Property $property)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $before = $property->status;

        $this->properties->reject($property, $data['reason'], Auth::guard('admin')->id());
        $this->audit->log('property.rejected', $property, ['status' => $before], ['reason' => $data['reason']]);

        $this->notifier->send($property->user, 'listing.reviewed', 'Your listing needs changes', "\"{$property->title}\": {$data['reason']}", route('listings.edit', $property));

        return redirect()->route('admin.properties.index')->with('success', 'Listing rejected.');
    }
}
