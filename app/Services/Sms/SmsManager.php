<?php

namespace App\Services\Sms;

class SmsManager
{
    public function gateway(): SmsGateway
    {
        return match (config('sms.driver')) {
            'http' => new HttpSmsGateway(config('sms.http')),
            'array' => new ArraySmsGateway,
            default => new LogSmsGateway,
        };
    }
}
