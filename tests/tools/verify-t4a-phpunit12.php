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
$check(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80400, 'PHP 8.3.x discovery runtime (' . PHP_VERSION . ')');
foreach (['dom','json','libxml','mbstring','phar','tokenizer','xml','xmlwriter'] as $extension) {
    $check(extension_loaded($extension), 'Required extension: ' . $extension);
}
$required = [
    'phpunit.xml', 'phpunit-10.xml', 'phpunit-11.xml', 'phpunit-12.xml', 'tests/bootstrap.php',
    'tests/tools/phpunit-9.5.8.phar', 'tests/tools/phpunit-10.phar', 'tests/tools/phpunit-11.phar',
    'tests/tools/phpunit-12.phar', 'tests/tools/phpunit-12.phar.sha256',
    'tests/tools/acquire-phpunit12.ps1', 'tests/tools/run-t3b-checks.cmd',
    'tests/tools/run-t4a-phpunit12-checks.cmd', 'tests/tools/run-t4a-checks.cmd',
    'tests/tools/verify-t4a-phpunit12.php'
];
foreach ($required as $file) { $check(is_file($root . '/' . $file), 'Required file: ' . $file); }
$expectedHash = '2c076d3d30f3bca762b13d996ad665d23220bc29afdb98a40387f7896b324195';
$phar = $root . '/tests/tools/phpunit-12.phar';
$check(is_file($phar) && hash_file('sha256', $phar) === $expectedHash, 'Pinned PHPUnit 12.5.35 SHA-256');
$manifest = is_file($root . '/tests/tools/phpunit-12.phar.sha256') ? trim((string) file_get_contents($root . '/tests/tools/phpunit-12.phar.sha256')) : '';
$check($manifest === $expectedHash . '  phpunit-12.phar', 'PHPUnit 12 checksum manifest');
$script = is_file($root . '/tests/tools/acquire-phpunit12.ps1') ? (string) file_get_contents($root . '/tests/tools/acquire-phpunit12.ps1') : '';
$check(str_contains($script, 'https://phar.phpunit.de/phpunit-12.5.35.phar') && str_contains($script, $expectedHash), 'PHPUnit 12 acquisition URL and hash pin');
$ignore = is_file($root . '/.gitignore') ? file($root . '/.gitignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$check(in_array('/tests/tools/phpunit-12.phar', $ignore, true), 'PHPUnit 12 PHAR ignored as runtime-only');
exec('git -C ' . escapeshellarg($root) . ' ls-files --error-unmatch -- ' . escapeshellarg('tests/tools/phpunit-12.phar') . ' 2>NUL', $trackedOutput, $trackedExit);
$check($trackedExit !== 0, 'PHPUnit 12 PHAR is not tracked');
if (is_file($phar)) {
    $output=[]; $exitCode=1;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar) . ' --version 2>&1', $output, $exitCode);
    $version=trim(implode("\n",$output)); echo '[INFO] '.$version.PHP_EOL;
    $check($exitCode === 0 && preg_match('/^PHPUnit 12\.5\.35\b/', $version) === 1, 'Pinned PHPUnit 12.5.35 runtime');
} else { $check(false, 'Pinned PHPUnit 12.5.35 runtime'); }
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$check(($composer['require']['php'] ?? null) === '>=7.4', 'Production PHP constraint remains >=7.4');
$check(($composer['require-dev']['phpunit/phpunit'] ?? null) === '9.5', 'Composer baseline remains PHPUnit 9.5');
$check(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 composer.lock');
$xml=(string)file_get_contents($root.'/phpunit-12.xml');
$check(str_contains($xml,'https://schema.phpunit.de/12.5/phpunit.xsd'),'PHPUnit 12.5 schema');
$check(str_contains($xml,'bootstrap="tests/bootstrap.php"'),'Shared bootstrap retained');
$check(str_contains($xml,'failOnDeprecation="true"'),'PHP deprecations fail the PHPUnit 12 lane');
$check(str_contains($xml,'failOnPhpunitDeprecation="true"'),'PHPUnit deprecations fail the PHPUnit 12 lane');
$check(str_contains($xml,'<directory suffix="Test.php">tests/unit</directory>'),'Unit suite scope retained');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', RecursiveDirectoryIterator::SKIP_DOTS));
$parsed=0; $parseFailures=[];
foreach($iterator as $file){
    if(!$file->isFile() || strtolower($file->getExtension())!=='php'){continue;}
    try{token_get_all((string)file_get_contents($file->getPathname()),TOKEN_PARSE);++$parsed;}
    catch(ParseError $error){$parseFailures[]=str_replace('\\','/',$file->getPathname()).': '.$error->getMessage();}
}
foreach($parseFailures as $failure){echo '[PARSE-FAIL] '.$failure.PHP_EOL;}
$check($parseFailures===[] && $parsed===99,'All 99 test-harness PHP files parse on PHP 8.3');
echo '[SUMMARY] checks='.$checks.', failures='.count($errors).PHP_EOL;
echo $errors ? "T4A_PREFLIGHT=FAIL\n" : "T4A_PREFLIGHT=PASS\n";
exit($errors ? 1 : 0);
