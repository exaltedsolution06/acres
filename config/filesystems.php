<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Public uploads (property photos, profile pictures, banners...).
        // Production on cPanel: UPLOADS_PATH=/home/account/public_html/uploads
        'uploads' => [
            'driver' => 'local',
            'root' => env('UPLOADS_PATH', public_path('uploads')),
            'url' => rtrim(env('ASSET_URL') ?: env('APP_URL', 'http://localhost'), '/').'/uploads',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Verification documents: never web-accessible, served only via an authorised admin route.
        'private_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/documents'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
