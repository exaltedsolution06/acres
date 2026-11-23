<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationDocument extends Model
{
    protected $guarded = [];

    public function request(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class, 'verification_request_id');
    }

    public function label(): string
    {
        foreach (config('trust.types') as $type) {
            if (isset($type['documents'][$this->doc_type])) {
                return $type['documents'][$this->doc_type];
            }
        }

        return $this->doc_type;
    }
}
