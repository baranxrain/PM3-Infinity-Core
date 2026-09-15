<?php

declare(strict_types=1);
namespace Tests\Unit\Compatibility;
use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
final class BrowserRuntimeHarnessTest extends TestCase
{
 private static function html():string{return (string)file_get_contents(PM_TEST_ROOT.'/tests/browser/u319-client-runtime.html');}
 private static function cmd():string{return (string)file_get_contents(PM_TEST_ROOT.'/tests/tools/run-u319-browser-checks.cmd');}
 public function testHarnessAndRunnerExist():void{self::assertFileExists(PM_TEST_ROOT.'/tests/browser/u319-client-runtime.html');self::assertFileExists(PM_TEST_ROOT.'/tests/tools/run-u319-browser-checks.cmd');}
 public function testHarnessHasSixteenRuntimeCases():void{self::assertSame(16,substr_count(self::html(),"checkCase('"));}
 public function testJsonContractsCoverValidAndMalformedPayloads():void{$s=self::html();self::assertGreaterThanOrEqual(3,substr_count($s,'JSON.parse('));self::assertStringContainsString('Malformed JSON fails closed',$s);}
 public function testCallbackResolverSupportsDottedPaths():void{$s=self::html();self::assertStringContainsString('callbackName.match(/[^.]+/g) || []',$s);self::assertStringContainsString('callback = callback[parts[index]];',$s);}
 public function testCallbackResolverPreservesReceiverAndArguments():void{self::assertStringContainsString('found.callback.apply(found.owner, args)',self::html());}
 public function testUnknownCallbacksFailClosed():void{self::assertStringContainsString("throw new Error('Unknown AJAX callback: ' + callbackName)",self::html());}
 public function testPagedTableHooksAcceptOnlyCallables():void{$s=self::html();self::assertStringContainsString("typeof hook === 'function'",$s);self::assertStringContainsString("typeof root[hook] === 'function'",$s);}
 public function testDomScriptExecutionIsOrderedAndCleaned():void{$s=self::html();self::assertStringContainsString('parent.appendChild(script); parent.removeChild(script);',$s);self::assertStringContainsString('DOM script order is synchronous',$s);self::assertStringContainsString('DOM script node is removed',$s);}
 public function testDirectConditionCoversTrueAndFalse():void{self::assertStringContainsString('Direct generated condition behavior',self::html());}
 public function testRunnerFindsSupportedBrowsersAndUsesHeadlessFallback():void{$s=self::cmd();self::assertStringContainsString('msedge.exe chrome.exe chromium.exe chromium',$s);self::assertStringContainsString('--headless=new',$s);self::assertStringContainsString('--headless --disable-gpu',$s);}
 public function testRunnerRequiresExactPassMarkers():void{$s=self::cmd();self::assertStringContainsString('Browser tests: 16, Passed: 16, Failures: 0',$s);self::assertStringContainsString('U319_BROWSER_ACCEPTANCE=PASS',$s);}
 public function testCompatibilityBudgetsRemainPinned():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);$c=CompatibilityLedger::counts($b['scope'],$b['patterns']);foreach($b['budgets']as$k=>$v)self::assertSame($v,$c[$k]['textual'],$k);foreach($b['codeBudgets']as$k=>$v)self::assertSame($v,$c[$k]['code'],$k);}
}
