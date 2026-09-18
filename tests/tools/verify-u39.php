<?php

declare(strict_types=1);
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;
$root = dirname(__DIR__, 2); $checks = 0;
$pass = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL); exit(1); } ++$checks; echo '[PASS] ' . $message . PHP_EOL; };
$required = ['gulliver/system/class.publisher.php','tests/fixtures/deprecation-budget.json','tests/fixtures/eval-inventory.json','tests/unit/Compatibility/PublisherEvalMigrationTest.php','tests/tools/verify-u39.php','tests/tools/run-u39-checks.cmd'];
foreach ($required as $relative) { $pass(is_file($root . '/' . $relative), 'Required U-3.9 file: ' . $relative); }
$pass(hash_file('sha256', $root . '/composer.json') === '708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2', 'Unchanged since U-1: composer.json');
$pass(hash_file('sha256', $root . '/composer.lock') === '913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033', 'Accepted T-4B PHP 8.3 Composer lock');
require_once $root . '/tests/bootstrap.php';
$target='gulliver/system/class.publisher.php'; $pattern='(?<![\\w$>-])eval\\s*\\('; $raw=PhpSourceScanner::read($root.'/'.$target); $code=PhpSourceScanner::codeOnlySource($raw);
$budget=json_decode((string)file_get_contents($root.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR); $inventory=json_decode((string)file_get_contents($root.'/tests/fixtures/eval-inventory.json'),true,512,JSON_THROW_ON_ERROR); $counts=CompatibilityLedger::counts($budget['scope'],['eval'=>$pattern]);
token_get_all($raw,TOKEN_PARSE); $pass(true,'class.publisher.php parses under PHP 8.1');
$pass(PhpSourceScanner::matchCount($code,$pattern)===0,'Publisher contains no executable eval sites');
$pass(strpos($raw,"eval( '\$G_FORM = new '")===false,'Old generated constructor eval was removed');
$pass(strpos($raw,'$templateClass = $Part[\'Template\'];')!==false,'Template class name is retained');
$pass(strpos($raw,'$G_FORM = new $templateClass($Part[\'File\'], $sPath);')!==false,'Dynamic class construction keeps both constructor arguments');
$pass(strpos($raw,"if (! class_exists( \$Part['Template'] ) || \$Part['Template'] === 'xmlform')")!==false,'Fallback branch gate is unchanged');
$pass(strpos($raw,"\$G_FORM = new Form( \$Part['File'], \$sPath, SYS_LANG, false );")!==false,'Fallback Form construction is unchanged');
$pass(($budget['_noteU39']??'')!=='','The U-3.9 eval ratchet movement is documented');
$pass($budget['budgets']['eval']===0,'Eval textual budget lowered to 19'); $pass($budget['codeBudgets']['eval']===0,'Eval executable budget lowered to 7');
$pass($counts['eval']['textual']===0,'Live textual eval count is 19'); $pass($counts['eval']['code']===0,'Live executable eval count is 7');
$pass($inventory['summary']['textual']===0,'Inventory textual eval count is 19'); $pass($inventory['summary']['executable']===0,'Inventory executable eval count is 7'); $pass($inventory['summary']['nonExecutable']===0,'Inventory non-executable eval count remains 12'); $pass($inventory['summary']['executableFiles']===0,'Inventory executable file count lowered to 4');
$pass(($inventory['categories']['gulliver_dynamic_runtime']['count']??null)===0,'Gulliver dynamic runtime category lowered to 0'); $pass(!array_key_exists($target,$inventory['perFileExecutable']),'Publisher is absent from per-file eval inventory');
$pass(count(array_filter($inventory['executableSites'],static fn(array $site):bool=>$site['file']===$target))===0,'Publisher has no listed executable eval site');
$pass($budget['budgets']['strftime']===140&&$budget['codeBudgets']['strftime']===0,'Strftime ratchet is unchanged'); $pass($budget['budgets']['utf8_encode_decode']===4&&$budget['codeBudgets']['utf8_encode_decode']===0,'UTF-8 ratchet is unchanged');
echo '[SUMMARY] '.$checks.' U-3.9 preflight checks passed.'.PHP_EOL;
