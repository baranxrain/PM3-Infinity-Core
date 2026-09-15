<?php

declare(strict_types=1);
namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use Tests\Support\CompatibilityLedger;
use Tests\Support\PhpSourceScanner;

require_once PM_TEST_ROOT . '/workflow/engine/classes/XMLWhereExpressionEvaluator.php';
require_once PM_TEST_ROOT . '/workflow/engine/src/ProcessMaker/BusinessModel/LegacyArrayLiteralParser.php';
require_once PM_TEST_ROOT . '/gulliver/system/class.xmlformSafeExpressionEvaluator.php';

final class ExpressionExecutionClosureTest extends TestCase
{
    private const TARGETS = ['workflow/engine/classes/XMLConnection.php','workflow/engine/classes/model/Event.php','workflow/engine/src/ProcessMaker/BusinessModel/Process.php'];
    private const PATTERN = '(?<![\\w$>-])eval\\s*\\(';
    private static function source(string $file): string { return PhpSourceScanner::read(PM_TEST_ROOT . '/' . $file); }

    public function testAllThreeTargetsContainNoExecutableEval(): void { foreach(self::TARGETS as $f) self::assertSame(0,PhpSourceScanner::matchCount(PhpSourceScanner::codeOnlySource(self::source($f)),self::PATTERN),$f); }
    public function testXmlConnectionUsesOneEvaluatorAtThreePaths(): void { $s=self::source(self::TARGETS[0]);self::assertStringContainsString("require_once __DIR__ . '/XMLWhereExpressionEvaluator.php';",$s);self::assertSame(3,substr_count($s,'XMLWhereExpressionEvaluator::evaluate($sqlWhereExpression, $res[$r])')); }
    public function testXmlEquality(): void { self::assertTrue(\XMLWhereExpressionEvaluator::evaluate("STATUS = 'OPEN'",['STATUS'=>'OPEN']));self::assertFalse(\XMLWhereExpressionEvaluator::evaluate("STATUS <> 'OPEN'",['STATUS'=>'OPEN'])); }
    public function testXmlBooleanPrecedence(): void { self::assertTrue(\XMLWhereExpressionEvaluator::evaluate('A = 1 OR B = 2 AND C = 3',['A'=>0,'B'=>2,'C'=>3]));self::assertFalse(\XMLWhereExpressionEvaluator::evaluate('A = 1 OR B = 2 AND C = 3',['A'=>0,'B'=>2,'C'=>0])); }
    public function testXmlParenthesesAndNot(): void { self::assertTrue(\XMLWhereExpressionEvaluator::evaluate('NOT (A = 1 OR B = 2)',['A'=>0,'B'=>0])); }
    public function testXmlNumericComparisons(): void { self::assertTrue(\XMLWhereExpressionEvaluator::evaluate('TOTAL >= 10 AND TOTAL < 20',['TOTAL'=>12]));self::assertTrue(\XMLWhereExpressionEvaluator::evaluate('TOTAL = -2',['TOTAL'=>-2])); }
    public function testXmlLike(): void { self::assertTrue(\XMLWhereExpressionEvaluator::evaluate("NAME LIKE 'ab%'",['NAME'=>'ABcd']));self::assertFalse(\XMLWhereExpressionEvaluator::evaluate("NAME LIKE 'ab%'",['NAME'=>'zabcd'])); }
    public function testXmlNotLike(): void { self::assertTrue(\XMLWhereExpressionEvaluator::evaluate("NAME NOT LIKE 'x%'",['NAME'=>'alpha'])); }
    public function testXmlUnknownFieldFailsClosed(): void { self::assertFalse(\XMLWhereExpressionEvaluator::evaluate('MISSING = 1',['KNOWN'=>1])); }
    public function testXmlRejectsExecutableSyntax(): void { foreach(['phpinfo()','system(\'id\')','$x == 1','A->x == 1'] as $x) self::assertFalse(\XMLWhereExpressionEvaluator::evaluate($x,['A'=>1])); }
    public function testXmlRejectsMalformedSyntax(): void { foreach(['','A =','(A = 1','A = 1 junk'] as $x) self::assertFalse(\XMLWhereExpressionEvaluator::evaluate($x,['A'=>1])); }
    public function testEventUsesSharedEvaluator(): void { $s=self::source(self::TARGETS[1]);self::assertStringContainsString("require_once dirname(__DIR__, 4) . '/gulliver/system/class.xmlformSafeExpressionEvaluator.php';",$s);self::assertStringContainsString('XmlFormSafeExpressionEvaluator::evaluate($sCondition)',$s);self::assertStringNotContainsString('$sCond =',$s); }
    public function testEventArithmeticBehavior(): void { self::assertTrue(\XmlFormSafeExpressionEvaluator::evaluate('(2 + 3) * 4 == 20'));self::assertFalse(\XmlFormSafeExpressionEvaluator::evaluate('10 / 0')); }
    public function testEventBooleanChainsConsumeAllTokens(): void { self::assertTrue(\XmlFormSafeExpressionEvaluator::evaluate('true || false || false'));self::assertFalse(\XmlFormSafeExpressionEvaluator::evaluate('false && true && true')); }
    public function testEventRejectsCallsAndVariables(): void { self::assertFalse(\XmlFormSafeExpressionEvaluator::evaluate('strlen(\'x\')'));self::assertFalse(\XmlFormSafeExpressionEvaluator::evaluate('$secret')); }
    public function testProcessUsesArrayParser(): void { $s=self::source(self::TARGETS[2]);self::assertStringContainsString('LegacyArrayLiteralParser::parse($fieldValue)',$s);self::assertStringContainsString('$arrayAux = is_array($parsedArray) ? $parsedArray : array();',$s); }
    public function testBracketLists(): void { self::assertSame(['A',2,true,null],\ProcessMaker\BusinessModel\LegacyArrayLiteralParser::parse("['A',2,true,null]")); }
    public function testLongArrayAndTrailingComma(): void { self::assertSame(['A','B'],\ProcessMaker\BusinessModel\LegacyArrayLiteralParser::parse("array('A','B',)")); }
    public function testAssociativeNestedArrays(): void { self::assertSame(['key'=>[1,2]],\ProcessMaker\BusinessModel\LegacyArrayLiteralParser::parse("['key'=>[1,2]]")); }
    public function testArrayScalarTypesAndEscapes(): void { $expression = "[-2,1.5,false,'a" . chr(92) . "'b']"; self::assertSame([-2,1.5,false,"a'b"],\ProcessMaker\BusinessModel\LegacyArrayLiteralParser::parse($expression)); }
    public function testArrayParserRejectsExecutableInput(): void { foreach(["system('id')",'[$x]','[new X]','[1','not-an-array'] as $x) self::assertNull(\ProcessMaker\BusinessModel\LegacyArrayLiteralParser::parse($x)); }
    public function testRatchetLeavesOnlyTwoTriggerSites(): void { $b=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/deprecation-budget.json'),true,512,JSON_THROW_ON_ERROR);$i=json_decode((string)file_get_contents(PM_TEST_ROOT.'/tests/fixtures/eval-inventory.json'),true,512,JSON_THROW_ON_ERROR);$c=CompatibilityLedger::counts($b['scope'],['eval'=>self::PATTERN]);self::assertSame(0,$c['eval']['textual']);self::assertSame(0,$c['eval']['code']);self::assertSame(0,$b['budgets']['eval']);self::assertSame(0,$b['codeBudgets']['eval']);self::assertArrayHasKey('_noteU316',$b);self::assertSame(0,$i['summary']['executableFiles']);self::assertSame(0,$i['categories']['dynamic_model_or_criteria']['count']);self::assertSame([],$i['perFileExecutable']);self::assertCount(0,$i['executableSites']); }
}
