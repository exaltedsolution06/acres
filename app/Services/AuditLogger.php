<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null): void
    {
        AdminAuditLog::create([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()->ip(),
        ]);
    }
}
