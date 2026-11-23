<?php

namespace App\Services\Sms;

class ArraySmsGateway implements SmsGateway
{
    /** @var array<int, array{mobile: string, message: string}> */
    public static array $messages = [];

    public function send(string $mobile, string $message): void
    {
        self::$messages[] = ['mobile' => $mobile, 'message' => $message];
    }

    public static function lastCode(?string $mobile = null): ?string
    {
        foreach (array_reverse(self::$messages) as $m) {
            if (($mobile === null || $m['mobile'] === $mobile) && preg_match('/\b(\d{6})\b/', $m['message'], $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
