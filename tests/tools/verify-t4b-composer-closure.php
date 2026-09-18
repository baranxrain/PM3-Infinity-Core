<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$errors=[];$checks=0;
$check=static function(bool $ok,string $message)use(&$errors,&$checks):void{echo($ok?'[PASS] ':'[FAIL] ').$message.PHP_EOL;if($ok){++$checks;}else{$errors[]=$message;}};
$check(PHP_SAPI==='cli','CLI runtime');
$check(PHP_VERSION_ID>=80300&&PHP_VERSION_ID<80400,'PHP 8.3.x closure runtime ('.PHP_VERSION.')');
foreach(['json','phar','tokenizer']as$ext){$check(extension_loaded($ext),'Required extension: '.$ext);}
foreach(['composer.json','composer.lock','tests/tools/verify-t4b-composer-closure.php','tests/tools/run-t4b-checks.cmd','tests/tools/README.md','tests/tools/DEPENDENCY-INVENTORY.md']as$f){$check(is_file($root.'/'.$f),'Required T-4B file: '.$f);}
$composer=json_decode((string)file_get_contents($root.'/composer.json'),true,512,JSON_THROW_ON_ERROR);
$lock=json_decode((string)file_get_contents($root.'/composer.lock'),true,512,JSON_THROW_ON_ERROR);
$check(($composer['require']['php']??null)==='>=7.4','Production PHP constraint remains >=7.4');
$check(($composer['require-dev']['phpunit/phpunit']??null)==='9.5','Composer PHPUnit baseline remains 9.5');
$check(($lock['content-hash']??null)==='080123d899a9a1dacbeb10a5d1f5fe04','Composer content-hash unchanged');
$check(hash_file('sha256',$root.'/composer.lock')==='913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033','Accepted T-4B composer.lock SHA-256');
$packages=[];foreach(['packages','packages-dev']as$section){foreach(($lock[$section]??[])as$p){$p['_section']=$section;$packages[$p['name']]=$p;}}
$expected=[
'nette/schema'=>['v1.2.5','7.1 - 8.3','0462f0166e823aad657c9224d0f849ecac1ba10a','packages'],
'nette/utils'=>['v3.2.10','>=7.2 <8.4','a4175c62652f2300c8017fb7e640f9ccb11648d2','packages'],
'phpspec/prophecy'=>['v1.18.0','^7.2 || 8.0.* || 8.1.* || 8.2.* || 8.3.*','d4f454f7e1193933f04e6500de3e79191648ed0c','packages-dev'],
'phpunit/phpunit'=>['9.5.0','>=7.3','8e16c225d57c3d6808014df6b1dd7598d0a5bbbe','packages-dev']];
foreach($expected as$name=>[$version,$php,$ref,$section]){$p=$packages[$name]??[];$check(($p['version']??null)===$version,$name.' exact version '.$version);$check(($p['require']['php']??null)===$php,$name.' exact PHP constraint');$check(($p['source']['reference']??null)===$ref,$name.' exact source reference');$check(($p['_section']??null)===$section,$name.' expected lock section');}
$check(count($lock['packages']??[])===106&&count($lock['packages-dev']??[])===41,'Lock cardinality 106 production / 41 development');
$runner=(string)file_get_contents($root.'/tests/tools/run-t4b-checks.cmd');$check(substr_count($runner,'call composer ')===3,'Composer batch commands return to the T-4B runner');
$tracked=[];$rc=1;exec('git -C '.escapeshellarg($root).' ls-files -- vendor/phpunit vendor/phpspec vendor/sebastian vendor/squizlabs vendor/wimg 2>NUL',$tracked,$rc);$check($rc===0&&$tracked===[],'Development vendor trees are not tracked');
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests',RecursiveDirectoryIterator::SKIP_DOTS));$parsed=0;$fail=[];foreach($it as$f){if(!$f->isFile()||strtolower($f->getExtension())!=='php')continue;try{token_get_all((string)file_get_contents($f->getPathname()),TOKEN_PARSE);++$parsed;}catch(ParseError$e){$fail[]=$f->getPathname().': '.$e->getMessage();}}foreach($fail as$f)echo'[PARSE-FAIL] '.$f.PHP_EOL;$check($fail===[]&&$parsed===99,'All 99 test-harness PHP files parse on PHP 8.3');
echo'[SUMMARY] checks='.$checks.', failures='.count($errors).PHP_EOL;echo$errors?"T4B_PREFLIGHT=FAIL
":"T4B_PREFLIGHT=PASS
";exit($errors?1:0);
