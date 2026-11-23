<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SiteVisit;
use App\Services\EnquiryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** The seeker's side: show interest, send an enquiry, track it, request visits. */
class EnquiryController extends Controller
{
    public function __construct(private EnquiryService $enquiries)
    {
    }

    public function interest(Request $request, Property $property)
    {
        $this->enquiries->showInterest($request->user(), $property);

        return back()->with('status', 'Interest sent. The poster has been notified.');
    }

    public function enquire(Request $request, Property $property)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->enquiries->sendEnquiry($request->user(), $property, $data['message']);

        return back()->with('status', 'Enquiry sent. You can track it under My enquiries.');
    }

    public function index(Request $request)
    {
        return view('enquiries.index', [
            'enquiries' => Enquiry::where('from_user_id', $request->user()->id)
                ->with(['property.locality', 'property.city', 'poster', 'visits'])->latest('last_activity_at')->paginate(15),
        ]);
    }

    public function close(Request $request, Enquiry $enquiry)
    {
        abort_unless($enquiry->from_user_id === $request->user()->id, 403);
        $this->enquiries->closeBySeeker($enquiry);

        return back()->with('status', 'Enquiry closed.');
    }

    public function requestVisit(Request $request, Enquiry $enquiry)
    {
        abort_unless($enquiry->from_user_id === $request->user()->id, 403);
        $data = $request->validate(['scheduled_at' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:300']]);

        $this->enquiries->requestVisit($enquiry, $request->user(), Carbon::parse($data['scheduled_at'], config('marketplace.timezone')), $data['note'] ?? null);

        return back()->with('status', 'Visit requested. You will be notified when the poster responds.');
    }

    /** confirm | decline | complete (poster) and cancel (either side). The service enforces who may do what. */
    public function visitAction(Request $request, SiteVisit $visit, string $action)
    {
        $this->enquiries->respondToVisit($visit, $request->user(), $action);

        return back()->with('status', 'Visit updated.');
    }
}
