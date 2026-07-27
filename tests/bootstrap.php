<?php

/*
 * PHPUnit bootstrap for the framework's OWN test suite (runs standalone, not
 * inside a host app). Unit tests need only the autoloader + global helpers;
 * Feature tests additionally boot a fixture Application (see IntegrationTestCase).
 */

error_reporting(E_ALL);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Dependencies are not installed. Run `composer install` in the framework repo.\n");
    exit(1);
}
require $autoload;

// The global helper functions in src/helpers.php are not PSR-4 autoloaded; load once.
require_once __DIR__ . '/../src/helpers.php';

// Point framework path helpers at the fixture "mini app" used by Feature tests.
$GLOBALS['base_path'] = __DIR__ . '/Fixtures/app';

// Deterministic 32-byte app key so Encrypter-backed features (encrypt()/decrypt(),
// remember-me tokens) work in tests.
$_ENV['APP_KEY'] = '0123456789abcdef0123456789abcdef';

// getallheaders() is an Apache/FPM SAPI function and is undefined under the CLI SAPI
// PHPUnit runs on. Feature tests fabricate requests from $_SERVER, so provide the
// standard polyfill (this is also what a worker runtime will need — see WRK-01).
if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}
