<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$basePath = dirname(__DIR__);
$temporaryPaths = [
    '/tmp/laravel/storage/framework/cache/data',
    '/tmp/laravel/storage/framework/sessions',
    '/tmp/laravel/storage/framework/views',
    '/tmp/laravel/storage/logs',
    '/tmp/laravel/bootstrap/cache',
];

foreach ($temporaryPaths as $path) {
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

foreach (['APP_PACKAGES_CACHE' => 'packages.php', 'APP_SERVICES_CACHE' => 'services.php', 'APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_EVENTS_CACHE' => 'events.php'] as $key => $filename) {
    $value = '/tmp/laravel/bootstrap/cache/'.$filename;
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

putenv('APP_DEBUG=false');
$_ENV['APP_DEBUG'] = 'false';
$_SERVER['APP_DEBUG'] = 'false';
putenv('SESSION_DRIVER=array');
$_ENV['SESSION_DRIVER'] = 'array';
$_SERVER['SESSION_DRIVER'] = 'array';
putenv('CACHE_STORE=array');
$_ENV['CACHE_STORE'] = 'array';
$_SERVER['CACHE_STORE'] = 'array';
putenv('LOG_CHANNEL=stderr');
$_ENV['LOG_CHANNEL'] = 'stderr';
$_SERVER['LOG_CHANNEL'] = 'stderr';

// Keep writable Laravel runtime state on Vercel's temporary filesystem.
putenv('APP_STORAGE=/tmp/laravel/storage');
$_ENV['APP_STORAGE'] = '/tmp/laravel/storage';
$_SERVER['APP_STORAGE'] = '/tmp/laravel/storage';

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->useStoragePath('/tmp/laravel/storage');

$kernel = $app->make(Kernel::class);
// The function lives in /api, but Laravel's public front controller is rooted at /.
// Keep Symfony from stripping the real /api prefix from incoming API routes.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $basePath.'/public/index.php';
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
