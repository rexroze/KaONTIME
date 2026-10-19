<?php

// Run locally; never deploy this provisioning helper as an HTTP endpoint.
$backend = dirname(__DIR__).'/backend';
chdir($backend);
require $backend.'/vendor/autoload.php';

$credentialsPath = $backend.'/.vercel/admin-credentials.env';
if (!is_dir(dirname($credentialsPath))) {
    mkdir(dirname($credentialsPath), 0700, true);
}
if (!file_exists($credentialsPath)) {
    $password = bin2hex(random_bytes(24));
    file_put_contents($credentialsPath, "ADMIN_EMAIL=admin@kaontime.local\nADMIN_PASSWORD={$password}\n");
    chmod($credentialsPath, 0600);
}
$credentials = Dotenv\Dotenv::createArrayBacked(dirname($credentialsPath), basename($credentialsPath))->load();
if (in_array('--production', $argv, true)) {
    Dotenv\Dotenv::createMutable($backend.'/.vercel', 'deploy.env')->load();
}
$app = require $backend.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
try {
    $status = $kernel->call('app:create-admin', [
        'email' => $credentials['ADMIN_EMAIL'],
        '--name' => 'KaONTIME Administrator',
        '--password' => $credentials['ADMIN_PASSWORD'],
        '--no-interaction' => true,
    ]);
    if ($status !== 0) {
        throw new RuntimeException('Provisioning returned a nonzero status.');
    }
    echo 'Administrator provisioned. Credentials are stored only in backend/.vercel/admin-credentials.env.', PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "Administrator provisioning failed. No credentials were printed.\n");
    exit(1);
}
