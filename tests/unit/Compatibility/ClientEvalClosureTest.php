<?php

declare(strict_types=1);
namespace Tests\Unit\Compatibility;
use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;
final class ClientEvalClosureTest extends TestCase
{
 private const P='(?<![\\w$>-])eval\\s*\\(';
 private const TARGETS=['gulliver/system/class.xmlform.php','gulliver/system/class.pagedTable.php','gulliver/system/class.headPublisher.php','workflow/engine/methods/cases/cases_Ajax.php','workflow/engine/methods/cases/ajaxListener.php','workflow/engine/src/ProcessMaker/BusinessModel/Process.php','workflow/engine/classes/model/FieldCondition.php'];
 private static function src(string$f):string{return PhpSourceScanner::read(PM_TEST_ROOT.'/'.$f);}
 public function testRawEvalLedgerIsZero():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);$c=CompatibilityLedger::counts($b['scope'],['eval'=>self::P]);self::assertSame(0,$c['eval']['textual']);self::assertSame(0,$c['eval']['code']);}
 public function testEvalBudgetIsZero():void{$b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);self::assertSame(0,$b['budgets']['eval']);self::assertSame(0,$b['codeBudgets']['eval']);self::assertArrayHasKey('_noteU318',$b);}
 public function testInventoryIsFullyEmpty():void{$i=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/eval-inventory.json'),true,512,JSON_THROW_ON_ERROR);self::assertSame(['textual'=>0,'executable'=>0,'nonExecutable'=>0,'executableFiles'=>0],$i['summary']);self::assertSame([],$i['perFileExecutable']);self::assertSame([],$i['executableSites']);}
 public function testXmlFormUsesJsonParseTwice():void{self::assertSame(2,substr_count(self::src(self::TARGETS[0]),'newcont = JSON.parse(response);'));}
 public function testXmlFormHasNoRawEvalToken():void{self::assertSame(0,PhpSourceScanner::matchCount(self::src(self::TARGETS[0]),self::P));}
 public function testCasesAjaxUsesJsonParse():void{self::assertStringContainsString('var response = JSON.parse(oRPC.xmlhttp.responseText);',self::src(self::TARGETS[3]));}
 public function testAjaxListenerUsesJsonParse():void{self::assertStringContainsString('var response = JSON.parse(oRPC.xmlhttp.responseText);',self::src(self::TARGETS[4]));}
 public function testAjaxCallbackResolvesDottedPaths():void{$s=self::src(self::TARGETS[4]);self::assertStringContainsString("callback_function.match(/[^.]+/g) || []",$s);self::assertStringContainsString('callback = callback[callbackParts[callbackIndex]];',$s);}
 public function testAjaxCallbackPreservesOwnerAndArguments():void{$s=self::src(self::TARGETS[4]);self::assertStringContainsString('callback.call(callbackOwner, http_request.responseXML);',$s);self::assertStringContainsString('callback.call(callbackOwner, http_request.responseText, id);',$s);}
 public function testAjaxCallbackFailsClosedForUnknownName():void{self::assertStringContainsString("throw new Error('Unknown AJAX callback: ' + callback_function);",self::src(self::TARGETS[4]));}
 public function testPagedTableHooksRequireCallables():void{$s=self::src(self::TARGETS[1]);self::assertStringContainsString("typeof beforeDelete==='function'",$s);self::assertStringContainsString("typeof afterDelete==='function'",$s);}
 public function testPagedTableHooksPreserveReceiver():void{$s=self::src(self::TARGETS[1]);self::assertStringContainsString('beforeDelete.call(pagedTable)',$s);self::assertStringContainsString('afterDelete.call(pagedTable)',$s);}
 public function testHeadPublisherUsesDomScriptExecution():void{$s=self::src(self::TARGETS[2]);self::assertStringContainsString("document.createElement('script')",$s);self::assertStringContainsString('parent.appendChild(script);parent.removeChild(script);',$s);}
 public function testHeadPublisherRoutesBothLoadGroups():void{self::assertSame(2,substr_count(self::src(self::TARGETS[2]),'processMakerExecuteScriptSource(ajax_function('));}
 public function testFieldConditionEmitsDirectExpression():void{$s=self::src(self::TARGETS[6]);self::assertStringContainsString('$sCode .= "if( {$sCondition} ) { ";',$s);}
 public function testFieldConditionNoLongerEscapesNestedSource():void{self::assertStringNotContainsString('$sCondition = addslashes( $sCondition );',self::src(self::TARGETS[6]));}
 public function testStaleProcessCommentWasRemoved():void{self::assertStringContainsString('Legacy string assignment is handled by LegacyArrayLiteralParser.',self::src(self::TARGETS[5]));}
 public function testEveryFormerTargetIsRawTokenFree():void{foreach(self::TARGETS as$f)self::assertSame(0,PhpSourceScanner::matchCount(self::src($f),self::P),$f);}
}
