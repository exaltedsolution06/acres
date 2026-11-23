<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Adjust to the folder that holds the Laravel application (OUTSIDE public_html).
$appPath = __DIR__.'/../realestate_app';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// Makes public_path(), asset() and the uploads disk resolve to public_html.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
