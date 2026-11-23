<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\AuditLogger;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundAdminController extends Controller
{
    public function __construct(private RefundService $refunds, private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'requested');

        return view('admin.billing.refunds', [
            'refunds' => Refund::with(['payment.user', 'requester'])->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('id')->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function approve(Refund $refund)
    {
        $this->refunds->approve($refund);
        $this->audit->log('refund.approved', $refund->payment, null, ['refund_id' => $refund->id]);

        return back()->with('success', 'Refund processed.');
    }

    public function reject(Request $request, Refund $refund)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']]);
        $this->refunds->reject($refund, $data['note']);
        $this->audit->log('refund.rejected', $refund->payment, null, ['refund_id' => $refund->id, 'note' => $data['note']]);

        return back()->with('success', 'Refund request declined.');
    }
}
