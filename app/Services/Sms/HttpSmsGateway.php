<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsGateway implements SmsGateway
{
    public function __construct(private array $config)
    {
    }

    public function send(string $mobile, string $message): void
    {
        if (empty($this->config['url'])) {
            throw new RuntimeException('SMS_URL is not configured.');
        }

        Http::timeout(10)
            ->withHeaders($this->config['headers'] ?? [])
            ->asForm()
            ->post($this->config['url'], array_merge($this->config['extra'] ?? [], [
                $this->config['to_field'] => '91'.$mobile,
                $this->config['message_field'] => $message,
            ]))
            ->throw();
    }
}
