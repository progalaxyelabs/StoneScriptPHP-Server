<?php

// Test bootstrap for the StoneScriptPHP-Server skeleton.
//
// This project root IS the real production layout: the framework's own bootstrap
// (loaded via composer's autoload.files) guesses ROOT_PATH correctly, so we only
// need the autoloader, plus a throwaway environment and keys.

date_default_timezone_set('UTC');

$dir = sys_get_temp_dir() . '/stonescript-skeleton-tests-' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $privatePem);
file_put_contents($dir . '/private.pem', $privatePem);
file_put_contents($dir . '/public.pem', openssl_pkey_get_details($key)['key']);
register_shutdown_function(static function () use ($dir): void {
    foreach (['private.pem', 'public.pem'] as $f) {
        @unlink($dir . '/' . $f);
    }
    @rmdir($dir);
});

foreach ([
    'JWT_ISSUER'              => 'https://api.test.invalid',
    'JWT_PRIVATE_KEY_PATH'    => $dir . '/private.pem',
    'JWT_PUBLIC_KEY_PATH'     => $dir . '/public.pem',
    'DB_GATEWAY_URL'          => 'http://127.0.0.1:9',
    'DB_GATEWAY_PLATFORM'     => 'testapp',
    'DB_GATEWAY_SCHEMA_NAME'  => 'main',
    'AUTH_MODE'               => 'external',
    'AUTH_SERVICE_URL'        => 'http://127.0.0.1:9',
    'AUTH_ISSUER'             => 'https://auth.test.invalid',
    'PLATFORM_CODE'           => 'testapp',
] as $name => $value) {
    putenv("$name=$value");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

// The framework also loads the project's real .env (Dotenv, immutable: a variable that is already set
// wins). Pin every key of the shipped .env.example first, so a developer's own .env cannot change
// test results; the throwaway values above take precedence over both.
foreach (file(__DIR__ . '/../.env.example', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m) === 1 && getenv($m[1]) === false) {
        $value = trim($m[2]);
        if (preg_match('/^(["\'])(.*)\1$/', $value, $q) === 1) {
            $value = $q[2];
        }
        putenv("{$m[1]}=$value");
        $_ENV[$m[1]] = $_SERVER[$m[1]] = $value;
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

// `vendor/bin/phpunit` loads composer's autoloader BEFORE this file, and the framework bootstrap it
// triggers has already built the Env singleton from the developer's real .env. Drop it so the next
// Env::get_instance() reads the throwaway values above.
\StoneScriptPHP\Env::resetForTesting();
