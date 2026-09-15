<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "The unit-test bootstrap is CLI-only.\n");
    exit(2);
}

if (!defined('PM_TEST_ROOT')) {
    define('PM_TEST_ROOT', dirname(__DIR__));
}

// This bootstrap deliberately does not load bootstrap/app.php, a workspace,
// Propel, Eloquent, or a database connection. PHPUnit's Composer autoloader is
// sufficient; individual smoke tests explicitly load only pure source files.
if (!defined('PM_TEST_DATABASE_BOOTSTRAPPED')) {
    define('PM_TEST_DATABASE_BOOTSTRAPPED', false);
}

date_default_timezone_set('UTC');
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require_once __DIR__ . '/Support/PhpSourceScanner.php';
require_once __DIR__ . '/Support/CompatibilityLedger.php';
