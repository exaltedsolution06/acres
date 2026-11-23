<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\AuditLogger;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VerificationAdminController extends Controller
{
    public function __construct(private VerificationService $verifications, private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        return view('admin.trust.verifications', [
            'requests' => VerificationRequest::with(['user', 'property'])->withCount('documents')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($request->type, fn ($q, $t) => $q->where('type', $t))
                ->orderBy('id', $status === 'pending' ? 'asc' : 'desc')->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => VerificationRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(VerificationRequest $verification)
    {
        $verification->load(['user.profile', 'property', 'documents']);

        return view('admin.trust.verification', ['request' => $verification]);
    }

    public function approve(VerificationRequest $verification)
    {
        $this->verifications->approve($verification, Auth::guard('admin')->id());
        $this->audit->log('verification.approved', $verification, null, ['type' => $verification->type, 'user_id' => $verification->user_id]);

        return redirect()->route('admin.verifications.index')->with('success', 'Approved.');
    }

    public function reject(Request $request, VerificationRequest $verification)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->verifications->reject($verification, $data['reason'], Auth::guard('admin')->id());
        $this->audit->log('verification.rejected', $verification, null, ['reason' => $data['reason']]);

        return redirect()->route('admin.verifications.index')->with('success', 'Rejected.');
    }

    public function revoke(Request $request, VerificationRequest $verification)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->verifications->revoke($verification, $data['reason'], Auth::guard('admin')->id());
        $this->audit->log('verification.revoked', $verification, null, ['reason' => $data['reason']]);

        return back()->with('success', 'Verification removed.');
    }

    public function download(VerificationDocument $document)
    {
        $this->audit->log('verification.document_viewed', $document->request, null, ['document_id' => $document->id]);

        return Storage::disk('private_documents')->download($document->path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
