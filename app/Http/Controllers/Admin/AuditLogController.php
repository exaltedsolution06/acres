<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AdminAuditLog::with('admin')
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.audit.index', ['logs' => $logs]);
    }
}
