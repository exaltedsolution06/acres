<?php

return [
    // log   : writes the message to the Laravel log (local development only; refused in production)
    // array : keeps messages in memory (automated tests)
    // http  : posts to any SMS provider's HTTP API (MSG91, Fast2SMS, Twilio-compatible gateways, ...)
    'driver' => env('SMS_DRIVER', 'log'),

    'http' => [
        'url' => env('SMS_URL'),
        'headers' => env('SMS_HEADERS') ? json_decode(env('SMS_HEADERS'), true) : [], // e.g. {"authkey":"..."}
        'to_field' => env('SMS_TO_FIELD', 'to'),
        'message_field' => env('SMS_MESSAGE_FIELD', 'message'),
        'extra' => env('SMS_EXTRA') ? json_decode(env('SMS_EXTRA'), true) : [],   // sender id, DLT template id, route...
    ],

    'otp' => [
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'sends_per_mobile' => 3,   // per 10 minutes
        'sends_per_ip' => 15,      // per hour
    ],
];
