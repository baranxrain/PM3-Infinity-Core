<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$fail=[];$checks=0;
$check=static function(bool $ok,string $msg)use(&$fail,&$checks):void{echo($ok?'[PASS] ':'[FAIL] ').$msg.PHP_EOL;if($ok)++$checks;else$fail[]=$msg;};
$check(PHP_SAPI==='cli','CLI runtime');
$check(PHP_VERSION_ID>=80300&&PHP_VERSION_ID<80400,'PHP 8.3.x runtime boundary ('.PHP_VERSION.')');
$files=['tests/unit/Bootstrap/RuntimeBaselineTest.php'=>'9264fae36dc7176cdcf01739f23487a918a06cc39cba9c6eb849a49e455d86c4','tests/unit/Compatibility/LegacyUtf8ContractTest.php'=>'e4faabfa9ea38f1cac59884e767093ac75ad183952c39b915e0cf5b3f99ff59b'];
foreach($files as$f=>$sha){$path=$root.'/'.$f;$check(is_file($path),'Required T-4C file: '.$f);$check(is_file($path)&&hash_file('sha256',$path)===$sha,'Exact T-4C SHA-256: '.$f);}
$r=(string)file_get_contents($root.'/tests/unit/Bootstrap/RuntimeBaselineTest.php');$l=(string)file_get_contents($root.'/tests/unit/Compatibility/LegacyUtf8ContractTest.php');
$check(str_contains($r,'testTheApprovedRuntimeIsPhp81Php82OrPhp83Cli'),'Runtime test names PHP 8.1/8.2/8.3 lanes');
$check(substr_count($r,'80400')===1&&!str_contains($r,'80300'),'Runtime test rejects PHP 8.4+, not PHP 8.3');
$check(substr_count($l,'80400')===1&&!str_contains($l,'80300'),'Legacy oracle accepts PHP 8.3 and rejects PHP 8.4+');
$check(hash_file('sha256',$root.'/composer.lock')==='913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033','T-4B Composer closure unchanged');
foreach(['tests/tools/run-t4b-checks.cmd','tests/tools/run-t4c-checks.cmd','tests/tools/verify-t4b-composer-closure.php','tests/tools/verify-t4c-php83-runtime.php']as$f)$check(is_file($root.'/'.$f),'Required cumulative file: '.$f);
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests',RecursiveDirectoryIterator::SKIP_DOTS));$parsed=0;$errors=[];foreach($it as$f){if(!$f->isFile()||strtolower($f->getExtension())!=='php')continue;try{token_get_all((string)file_get_contents($f->getPathname()),TOKEN_PARSE);++$parsed;}catch(ParseError$e){$errors[]=$f->getPathname().': '.$e->getMessage();}}foreach($errors as$e)echo'[PARSE-FAIL] '.$e.PHP_EOL;$check($errors===[]&&$parsed===99,'All 99 test-harness PHP files parse on PHP 8.3');
echo'[SUMMARY] checks='.$checks.', failures='.count($fail).PHP_EOL;echo$fail?"T4C_PREFLIGHT=FAIL
":"T4C_PREFLIGHT=PASS
";exit($fail?1:0);
