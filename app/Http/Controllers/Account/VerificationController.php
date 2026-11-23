<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    public function __construct(private VerificationService $verifications)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $types = collect(config('trust.types'))->filter(fn ($t) => ! $t['role'] || $user->hasRole($t['role']));
        $properties = Property::where('user_id', $user->id)->whereNotIn('status', [Property::ARCHIVED, Property::SOLD, Property::RENTED])->latest('id')->get();

        return view('verification.index', [
            'types' => $types,
            'properties' => $properties,
            'requests' => VerificationRequest::where('user_id', $user->id)->with(['documents', 'property'])->latest('id')->get(),
            'until' => [
                'owner' => $user->owner_verified_until?->isFuture() ? $user->owner_verified_until : null,
                'broker' => $user->broker_verified_until?->isFuture() ? $user->broker_verified_until : null,
            ],
            'retention' => config('trust.document_retention_days'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('trust.types')))],
            'property' => ['nullable', 'string', 'size:8'],
            'note' => ['nullable', 'string', 'max:500'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config('trust.max_file_kb')],
        ]);

        $property = ! empty($data['property']) ? Property::where('public_id', $data['property'])->first() : null;

        $this->verifications->submit($request->user(), $data['type'], $request->file('documents', []) ?? [], $property, $data['note'] ?? null);

        return back()->with('status', 'Request sent. We will review your documents and let you know.');
    }

    /** Only the applicant can download their own documents. Served as an attachment from private storage. */
    public function download(Request $request, VerificationDocument $document)
    {
        abort_unless($document->request->user_id === $request->user()->id, 403);

        return Storage::disk('private_documents')->download($document->path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
