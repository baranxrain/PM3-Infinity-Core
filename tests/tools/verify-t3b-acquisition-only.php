<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$errors = [];
$checks = 0;
$check = static function (bool $ok, string $message) use (&$errors, &$checks): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL;
    if ($ok) { ++$checks; } else { $errors[] = $message; }
};
$check(PHP_SAPI === 'cli', 'CLI runtime');
$check(PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80400, 'PHP 8.2/8.3 runtime (' . PHP_VERSION . ')');
foreach (['json','phar','tokenizer'] as $extension) { $check(extension_loaded($extension), 'Required extension: ' . $extension); }
$pins = [
    '9' => ['file'=>'phpunit-9.5.8.phar','manifest'=>'phpunit-9.5.8.phar.sha256','script'=>'acquire-phpunit9.ps1','hash'=>'11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517','version'=>'/^PHPUnit 9\\.5\\.8\\b/','url'=>'https://phar.phpunit.de/phpunit-9.5.8.phar'],
    '10' => ['file'=>'phpunit-10.phar','manifest'=>'phpunit-10.phar.sha256','script'=>'acquire-phpunit10.ps1','hash'=>'a823d916151f628dd9943ccc81a98bcfbba9c5babf53f27be6c7dccc89f8ee23','version'=>'/^PHPUnit 10\\.5\\.64\\b/','url'=>'https://phar.phpunit.de/phpunit-10.5.64.phar'],
    '11' => ['file'=>'phpunit-11.phar','manifest'=>'phpunit-11.phar.sha256','script'=>'acquire-phpunit11.ps1','hash'=>'b20ea78f38bc6abccc96ace605c471b1d11912ad6f0285c74415919050d234a6','version'=>'/^PHPUnit 11\\.5\\.49\\b/','url'=>'https://phar.phpunit.de/phpunit-11.5.49.phar'],
];
$ignore = is_file($root . '/.gitignore') ? file($root . '/.gitignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
foreach ($pins as $major => $pin) {
    $relative = 'tests/tools/' . $pin['file'];
    $phar = $root . '/' . $relative;
    $manifest = $root . '/tests/tools/' . $pin['manifest'];
    $script = $root . '/tests/tools/' . $pin['script'];
    $check(is_file($manifest), 'PHPUnit ' . $major . ' checksum manifest');
    $check(trim((string) @file_get_contents($manifest)) === $pin['hash'] . '  ' . $pin['file'], 'PHPUnit ' . $major . ' manifest pin');
    $source = is_file($script) ? (string) file_get_contents($script) : '';
    $check($source !== '', 'PHPUnit ' . $major . ' acquisition script');
    $check(strpos($source, $pin['url']) !== false && strpos($source, $pin['hash']) !== false, 'PHPUnit ' . $major . ' acquisition URL and hash pin');
    $check(in_array('/' . $relative, $ignore, true), 'PHPUnit ' . $major . ' PHAR ignored as runtime-only');
    exec('git -C ' . escapeshellarg($root) . ' ls-files --error-unmatch -- ' . escapeshellarg($relative) . ' 2>NUL', $trackedOutput, $trackedExit);
    $check($trackedExit !== 0, 'PHPUnit ' . $major . ' PHAR is not tracked');
    $check(is_file($phar) && hash_file('sha256', $phar) === $pin['hash'], 'PHPUnit ' . $major . ' locally acquired SHA-256');
    $versionOutput = [];
    $versionExit = 1;
    if (is_file($phar)) { exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar) . ' --version 2>&1', $versionOutput, $versionExit); }
    $version = trim(implode("\n", $versionOutput));
    echo '[INFO] ' . $version . PHP_EOL;
    $check($versionExit === 0 && preg_match($pin['version'], $version) === 1, 'PHPUnit ' . $major . ' pinned runtime version');
}
foreach (['tests/tools/acquire-phpunit-all.ps1','tests/tools/apply-t3b-acquisition-only.cmd','tests/tools/run-t3b-checks.cmd','tests/tools/verify-t3b-acquisition-only.php'] as $relative) {
    $check(is_file($root . '/' . $relative), 'Required T-3B file: ' . $relative);
}
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$check(($composer['require']['php'] ?? null) === '>=7.4', 'Production PHP constraint remains >=7.4');
$check(($composer['require-dev']['phpunit/phpunit'] ?? null) === '9.5', 'Composer baseline remains PHPUnit 9.5');
$check(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 composer.lock');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS));
$parsed = 0; $parseFailures = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
    try { token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE); ++$parsed; }
    catch (ParseError $error) { $parseFailures[] = str_replace('\\', '/', $file->getPathname()) . ': ' . $error->getMessage(); }
}
foreach ($parseFailures as $failure) { echo '[PARSE-FAIL] ' . $failure . PHP_EOL; }
$check($parseFailures === [] && $parsed === 99, 'All 99 test-harness PHP files parse on PHP 8.2/8.3');
echo '[SUMMARY] checks=' . $checks . ', failures=' . count($errors) . PHP_EOL;
echo $errors ? "T3B_PREFLIGHT=FAIL\n" : "T3B_PREFLIGHT=PASS\n";
exit($errors ? 1 : 0);
