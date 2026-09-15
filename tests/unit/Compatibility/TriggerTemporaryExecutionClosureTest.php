<?php

declare(strict_types=1);
namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/classes/PMScriptTemporaryExecutionTrait.php';

final class TriggerTemporaryRunnerFixture
{
    use \PMScriptTemporaryExecutionTrait;
    public $value = null;
    public function runBody(string $script, string $code = ''): void { $this->executeTriggerScript($script, $code); }
    public function runCondition(string $expression): bool { return $this->evaluateTriggerConditionScript('$bResult = ' . $expression . ';'); }
}

final class TriggerTemporaryExecutionClosureTest extends TestCase
{
    private const TARGET='workflow/engine/classes/class.pmScript.php';
    private const TRAIT_FILE='workflow/engine/classes/PMScriptTemporaryExecutionTrait.php';
    private const PATTERN='(?<![\\w$>-])eval\\s*\\(';
    private static function source(string $file):string{return PhpSourceScanner::read(PM_TEST_ROOT.'/'.$file);}

    public function testPmscriptContainsNoExecutableEval():void{self::assertSame(0,PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource(self::source(self::TARGET)),self::PATTERN));}
    public function testPmscriptWiresTheTemporaryTraitOnce():void{$s=self::source(self::TARGET);self::assertSame(1,substr_count($s,'use PMScriptTemporaryExecutionTrait;'));self::assertStringContainsString("require_once __DIR__ . '/PMScriptTemporaryExecutionTrait.php';",$s);}
    public function testBothLegacySitesUseNamedBridgeMethods():void{$s=self::source(self::TARGET);self::assertStringContainsString('$this->executeTriggerScript($sScript, $sCode);',$s);self::assertStringContainsString('$bResult = $this->evaluateTriggerConditionScript($sScript);',$s);}
    public function testBridgeUsesOsOwnedTemporaryFile():void{$s=self::source(self::TRAIT_FILE);self::assertStringContainsString('$temporaryScript = tmpfile();',$s);self::assertStringContainsString('$metadata = stream_get_meta_data($temporaryScript);',$s);}
    public function testBridgeWritesFlushesAndIncludesTheFile():void{$s=self::source(self::TRAIT_FILE);self::assertStringContainsString('fwrite($temporaryScript, substr($source, $offset))',$s);self::assertStringContainsString('fflush($temporaryScript)',$s);self::assertStringContainsString('include $metadata[\'uri\']',$s);}
    public function testBridgeAlwaysClosesResource():void{$s=self::source(self::TRAIT_FILE);self::assertStringContainsString('} finally {',$s);self::assertSame(2,substr_count($s,'fclose($temporaryScript);'));}
    public function testTriggerBodyCanMutateConsumingObject():void{$f=new TriggerTemporaryRunnerFixture();$f->runBody('$this->value = 42;');self::assertSame(42,$f->value);}
    public function testTriggerBodyPreservesCodeVariableScope():void{$f=new TriggerTemporaryRunnerFixture();$f->runBody('$this->value = $sCode;','ORIGINAL');self::assertSame('ORIGINAL',$f->value);}
    public function testTriggerBodyPreservesBufferedOutput():void{$f=new TriggerTemporaryRunnerFixture();ob_start();$f->runBody("echo 'bridge-output';");$out=ob_get_clean();self::assertSame('bridge-output',$out);}
    public function testConditionReturnsTrue():void{$f=new TriggerTemporaryRunnerFixture();self::assertTrue($f->runCondition('2 + 3 === 5'));}
    public function testConditionReturnsFalse():void{$f=new TriggerTemporaryRunnerFixture();self::assertFalse($f->runCondition('2 + 3 === 6'));}
    public function testConditionCanReadConsumingObject():void{$f=new TriggerTemporaryRunnerFixture();$f->value='OPEN';self::assertTrue($f->runCondition('$this->value === \'OPEN\''));}
    public function testConditionResultIsNormalizedToBoolean():void{$f=new TriggerTemporaryRunnerFixture();self::assertTrue($f->runCondition("'non-empty'"));self::assertFalse($f->runCondition("''"));}
    public function testInvalidSourceThrowsAndNextExecutionStillWorks():void{$f=new TriggerTemporaryRunnerFixture();try{$f->runBody('this is invalid php');self::fail('Invalid source must throw.');}catch(\ParseError $e){self::assertNotSame('',$e->getMessage());}$f->runBody('$this->value = 7;');self::assertSame(7,$f->value);}
    public function testInventoryAndRatchetReachExecutableZero():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);$i=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/eval-inventory.json'),true,512,JSON_THROW_ON_ERROR);$c=CompatibilityLedger::counts($b['scope'],['eval'=>self::PATTERN]);self::assertSame(0,$c['eval']['textual']);self::assertSame(0,$c['eval']['code']);self::assertSame(0,$b['budgets']['eval']);self::assertSame(0,$b['codeBudgets']['eval']);self::assertSame(0,$i['summary']['executable']);self::assertSame(0,$i['summary']['executableFiles']);self::assertSame([],$i['perFileExecutable']);self::assertSame([],$i['executableSites']);}
    public function testNonExecutableAndOtherBudgetsStayPinned():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);$i=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/eval-inventory.json'),true,512,JSON_THROW_ON_ERROR);self::assertSame(0,$i['summary']['nonExecutable']);self::assertSame(140,$b['budgets']['strftime']);self::assertSame(4,$b['budgets']['utf8_encode_decode']);}
}
