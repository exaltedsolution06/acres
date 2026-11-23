<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class LogSmsGateway implements SmsGateway
{
    public function send(string $mobile, string $message): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SMS_DRIVER=log is not allowed in production. Configure SMS_DRIVER=http.');
        }

        Log::info("SMS to {$mobile}: {$message}");
    }
}
