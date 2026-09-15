<?php

declare(strict_types=1);
namespace Tests\Unit\Compatibility;
use PHPUnit\Framework\TestCase;use Tests\Support\CompatibilityLedger;use Tests\Support\PhpSourceScanner;
final class PmScriptTriggerEvalMigrationTest extends TestCase
{
 private const TARGET='workflow/engine/classes/class.pmScript.php';private const PATTERN='(?<![\\w$>-])eval\\s*\\(';
 private static function source():string{return PhpSourceScanner::read(PM_TEST_ROOT.'/'.self::TARGET);}private static function inventory():array{return json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/eval-inventory.json'),true,512,JSON_THROW_ON_ERROR);}
 public function testGeneratedFieldInitialisationAndBodyExecutionSitesAreClosed():void{self::assertSame(0,PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource(self::source()),self::PATTERN));}
 public function testPmscriptUsesTemporaryExecutionTrait():void{$s=self::source();self::assertStringContainsString("require_once __DIR__ . '/PMScriptTemporaryExecutionTrait.php';",$s);self::assertStringContainsString('use PMScriptTemporaryExecutionTrait;',$s);self::assertStringContainsString('$this->executeTriggerScript($sScript, $sCode);',$s);self::assertStringContainsString('$bResult = $this->evaluateTriggerConditionScript($sScript);',$s);}
 public function testDirectInitialisationHelpersRemainPresent():void{$c=PhpSourceScanner::codeOnlySource(self::source());self::assertSame(1,PhpSourceScanner::matchCount($c,'function\\s+initialiseTriggerFieldBase\\s*\\('));self::assertSame(1,PhpSourceScanner::matchCount($c,'function\\s+initialiseTriggerFieldPath\\s*\\('));self::assertSame(1,PhpSourceScanner::matchCount($c,'function\\s+triggerFieldPathSegments\\s*\\('));}
 public function testEvalRatchetIsFullyClosed():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);$c=CompatibilityLedger::counts($b['scope'],['eval'=>self::PATTERN]);self::assertSame(0,$c['eval']['textual']);self::assertSame(0,$c['eval']['code']);self::assertSame(0,$b['budgets']['eval']);self::assertSame(0,$b['codeBudgets']['eval']);self::assertArrayHasKey('_noteU317',$b);}
 public function testEvalInventoryContainsNoExecutableSite():void{$i=self::inventory();self::assertSame(0,$i['summary']['textual']);self::assertSame(0,$i['summary']['executable']);self::assertSame(0,$i['summary']['nonExecutable']);self::assertSame(0,$i['summary']['executableFiles']);self::assertSame(0,$i['categories']['trigger_script_eval']['count']);self::assertSame([],$i['perFileExecutable']);self::assertSame([],$i['executableSites']);}
 public function testOtherCompatibilityRatchetsAreUnaffected():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);self::assertSame(140,$b['budgets']['strftime']);self::assertSame(4,$b['budgets']['utf8_encode_decode']);}
}
