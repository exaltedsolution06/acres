<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

class FeatureNotAvailableException extends RuntimeException
{
    public function __construct(public readonly string $featureKey, string $message = '')
    {
        parent::__construct($message ?: 'This feature is not available on your current plan.');
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'feature' => $this->featureKey], 403);
        }

        return back()->withErrors(['feature' => $this->getMessage()]);
    }
}
