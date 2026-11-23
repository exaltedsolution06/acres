<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

class LimitExceededException extends RuntimeException
{
    public function __construct(public readonly string $featureKey, public readonly ?int $limit = null)
    {
        parent::__construct('You have reached your limit for this action. Upgrade your plan or try again tomorrow.');
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'feature' => $this->featureKey, 'limit' => $this->limit], 429);
        }

        return back()->withErrors(['limit' => $this->getMessage()]);
    }
}
