<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Services\ContactAccessService;
use App\Services\EnquiryService;
use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The poster's side: every enquiry on their listings, plus the lead pipeline for plans with lead_management. */
class LeadController extends Controller
{
    public function __construct(private EnquiryService $enquiries, private PermissionService $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $leads = Enquiry::where('to_user_id', $user->id)->with(['seeker', 'property'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->property, fn ($q, $id) => $q->whereHas('property', fn ($p) => $p->where('public_id', $id)))
            ->when($request->boolean('due'), fn ($q) => $q->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay()))
            ->latest('last_activity_at')->paginate(20)->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'counts' => Enquiry::where('to_user_id', $user->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Request $request, Enquiry $enquiry)
    {
        $this->authorizePoster($request, $enquiry);
        $enquiry->load(['seeker', 'property.locality', 'property.city', 'notes', 'visits']);

        return view('leads.show', [
            'enquiry' => $enquiry,
            'canManage' => $this->permissions->can($request->user(), 'lead_management', $enquiry->property->posted_by),
            'contact' => session('contact'),
        ]);
    }

    public function status(Request $request, Enquiry $enquiry)
    {
        $this->authorizePoster($request, $enquiry);
        $data = $request->validate(['status' => ['required', 'string']]);
        $this->enquiries->setStatus($enquiry, $data['status'], $request->user());

        return back()->with('status', 'Status updated.');
    }

    public function note(Request $request, Enquiry $enquiry)
    {
        $this->authorizePoster($request, $enquiry);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $this->enquiries->addNote($enquiry, $request->user(), $data['body']);

        return back()->with('status', 'Note added.');
    }

    public function followUp(Request $request, Enquiry $enquiry)
    {
        $this->authorizePoster($request, $enquiry);
        $data = $request->validate(['follow_up_at' => ['nullable', 'date'], 'follow_up_note' => ['nullable', 'string', 'max:300']]);
        $at = ! empty($data['follow_up_at']) ? Carbon::parse($data['follow_up_at'], config('marketplace.timezone')) : null;
        $this->enquiries->setFollowUp($enquiry, $request->user(), $at, $data['follow_up_note'] ?? null);

        return back()->with('status', $at ? 'Follow-up scheduled.' : 'Follow-up cleared.');
    }

    public function scheduleVisit(Request $request, Enquiry $enquiry)
    {
        $this->authorizePoster($request, $enquiry);
        $data = $request->validate(['scheduled_at' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:300']]);
        $this->enquiries->requestVisit($enquiry, $request->user(), Carbon::parse($data['scheduled_at'], config('marketplace.timezone')), $data['note'] ?? null);

        return back()->with('status', 'Visit scheduled and the visitor notified.');
    }

    /** The seeker's number, under the poster's own plan rules (daily contact allowance, credits). */
    public function contact(Request $request, Enquiry $enquiry, ContactAccessService $contacts)
    {
        $this->authorizePoster($request, $enquiry);
        $result = $contacts->reveal($request->user(), $enquiry->seeker, $enquiry->property_id, true, $request->ip());

        return redirect()->route('leads.show', $enquiry)->with('contact', $result);
    }

    /** Broker performance: needs the broker_crm feature. */
    public function performance(Request $request)
    {
        $user = $request->user();
        $this->permissions->assert($user, 'broker_crm', 'broker');

        $byStatus = Enquiry::where('to_user_id', $user->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = $byStatus->sum();

        return view('leads.performance', [
            'byStatus' => $byStatus,
            'total' => $total,
            'conversion' => $total ? round(($byStatus['converted'] ?? 0) / $total * 100, 1) : 0,
            'topProperties' => Enquiry::where('to_user_id', $user->id)->join('properties', 'properties.id', '=', 'enquiries.property_id')
                ->groupBy('properties.id', 'properties.title', 'properties.public_id')->orderByDesc('total')->limit(10)
                ->get(['properties.title', 'properties.public_id', DB::raw('count(*) as total')]),
        ]);
    }

    private function authorizePoster(Request $request, Enquiry $enquiry): void
    {
        abort_unless($enquiry->to_user_id === $request->user()->id, 403);
    }
}
