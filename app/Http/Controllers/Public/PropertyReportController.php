<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyReportController extends Controller
{
    public function store(Request $request, Property $property, ReportService $reports)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(config('trust.report_reasons')))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $reports->create($request->user(), $property, $data['reason'], $data['details'] ?? null);

        return back()->with('status', 'Thank you. Our team will review your report.');
    }
}
