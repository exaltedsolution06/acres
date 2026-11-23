<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyReport;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportAdminController extends Controller
{
    public function __construct(private ReportService $reports, private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'open');

        return view('admin.trust.reports', [
            'reports' => PropertyReport::with(['property.user', 'reporter'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($request->reason, fn ($q, $r) => $q->where('reason', $r))
                ->latest('id')->paginate(20)->withQueryString(),
            'status' => $status,
            'openPerProperty' => PropertyReport::where('status', 'open')->selectRaw('property_id, count(*) as n')->groupBy('property_id')->pluck('n', 'property_id'),
            'repeat' => PropertyReport::join('properties', 'properties.id', '=', 'property_reports.property_id')->join('users', 'users.id', '=', 'properties.user_id')
                ->where('property_reports.created_at', '>', now()->subDays(30))->groupBy('users.id', 'users.name')
                ->havingRaw('count(*) >= ?', [config('trust.repeat_offender_threshold')])->orderByDesc('total')
                ->get(['users.id', 'users.name', DB::raw('count(*) as total')]),
        ]);
    }

    public function show(PropertyReport $report)
    {
        $report->load(['property.user', 'reporter']);

        return view('admin.trust.report', [
            'report' => $report,
            'siblings' => PropertyReport::with('reporter')->where('property_id', $report->property_id)->where('id', '!=', $report->id)->latest('id')->get(),
        ]);
    }

    public function dismiss(Request $request, PropertyReport $report)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->reports->dismiss($report, $data['note'] ?? null, Auth::guard('admin')->id());
        $this->audit->log('report.dismissed', $report, null, ['note' => $data['note'] ?? null]);

        return redirect()->route('admin.reports.index')->with('success', 'Report dismissed.');
    }

    public function action(Request $request, PropertyReport $report)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $takeDown = $request->boolean('take_down');
        $suspend = $request->boolean('suspend_poster');

        $this->reports->action($report, $data['note'] ?? null, $takeDown, $suspend, Auth::guard('admin')->id());
        $this->audit->log('report.actioned', $report, null, ['take_down' => $takeDown, 'suspend_poster' => $suspend]);

        return redirect()->route('admin.reports.index')->with('success', 'Action recorded.');
    }
}
