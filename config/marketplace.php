<?php

return [
    // Daily limits reset at local midnight in this timezone.
    'timezone' => env('MARKETPLACE_TIMEZONE', 'Asia/Kolkata'),

    'roles' => ['owner', 'tenant', 'buyer', 'broker'],

    'upload_dirs' => ['properties', 'profiles', 'brokers', 'documents', 'banners', 'categories'],
];
