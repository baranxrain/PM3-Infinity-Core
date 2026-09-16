<?php
declare(strict_types=1);
$root=dirname(__DIR__,2); $errors=[];
$check=function(bool $ok,string $msg)use(&$errors):void{echo ($ok?'[PASS] ':'[FAIL] ').$msg."\n";if(!$ok)$errors[]=$msg;};
$check(PHP_MAJOR_VERSION===8&&PHP_MINOR_VERSION===1,'PHP 8.1 lane is active');
$required=['phpunit-10.xml','tests/bootstrap.php','tests/tools/phpunit-9.5.8.phar','tests/tools/phpunit-10.phar','tests/tools/phpunit-10.phar.sha256','tests/tools/run-u319-checks.cmd'];
foreach($required as $f)$check(is_file($root.'/'.$f),'Required file: '.$f);
$p9=$root.'/tests/tools/phpunit-9.5.8.phar';
if(is_file($p9))$check(hash_file('sha256',$p9)==='11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517','PHPUnit 9.5.8 PHAR unchanged');
$p10=$root.'/tests/tools/phpunit-10.phar';$mf=$root.'/tests/tools/phpunit-10.phar.sha256';
if(is_file($p10)&&is_file($mf)){
 preg_match('/^[a-f0-9]{64}/i',trim((string)file_get_contents($mf)),$m);$expected=strtolower($m[0]??'');$actual=hash_file('sha256',$p10);
 $check($expected!==''&&hash_equals($expected,$actual),'PHPUnit 10 discovery SHA-256 matches');
 exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($p10).' --version 2>&1',$out,$rc);$v=trim(implode("\n",$out));echo '[INFO] '.$v."\n";
 $check($rc===0&&preg_match('/^PHPUnit 10\./',$v)===1,'Runner identifies as PHPUnit 10.x');
}
$xml=is_file($root.'/phpunit-10.xml')?(string)file_get_contents($root.'/phpunit-10.xml'):'';
$check(strpos($xml,'schema.phpunit.de/10.5/phpunit.xsd')!==false,'PHPUnit 10.5 XML schema');
$check(strpos($xml,'<source>')!==false&&strpos($xml,'<coverage')===false,'PHPUnit 10 source syntax');
$c=json_decode((string)file_get_contents($root.'/composer.json'),true);
$check(($c['require-dev']['phpunit/phpunit']??null)==='9.5','Composer baseline remains PHPUnit 9.5');
echo $errors?'T1A_PREFLIGHT=FAIL'."\n":'T1A_PREFLIGHT=PASS'."\n";exit($errors?1:0);
