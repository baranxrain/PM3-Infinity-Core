<?php
namespace ProcessMaker\BusinessModel;
/** Parses PHP-style array literals without executing source code. */
final class LegacyArrayLiteralParser
{
 private $tokens=array();private $position=0;
 public static function parse($expression){try{$t=self::tokenize((string)$expression);if($t===null||$t===array())return null;$p=new self($t);$v=$p->parseArray();return$p->position===count($p->tokens)?$v:null;}catch(\Throwable $e){return null;}}
 private function __construct(array $t){$this->tokens=$t;}
 private static function tokenize($s){$t=array();$n=strlen($s);$i=0;while($i<$n){if(preg_match('/\\G\\s+/A',$s,$m,0,$i)){$i+=strlen($m[0]);continue;}$c=$s[$i];if($c==="'"||$c==='"'){$q=$c;$i++;$v='';$closed=false;while($i<$n){$c=$s[$i++];if($c===$q){$closed=true;break;}if($c==='\\'&&$i<$n){$x=$s[$i++];$v.=($q==="'"&&$x!=="'"&&$x!=='\\')?'\\'.$x:($q==='"'?stripcslashes('\\'.$x):$x);}else$v.=$c;}if(!$closed)return null;$t[]=array('value',$v);continue;}if(preg_match('/\\G-?(?:\\d+\\.\\d+|\\d+)/A',$s,$m,0,$i)){$x=$m[0];$t[]=array('value',strpos($x,'.')===false?(int)$x:(float)$x);$i+=strlen($x);continue;}if(preg_match('/\\G(?:true|false|null|array)\\b/Ai',$s,$m,0,$i)){$w=strtolower($m[0]);$t[]=$w==='array'?array('symbol','array'):array('value',$w==='true'?true:($w==='false'?false:null));$i+=strlen($m[0]);continue;}$ok=false;foreach(array('=>','[',']','(',')',',')as$x){if(substr($s,$i,strlen($x))===$x){$t[]=array('symbol',$x);$i+=strlen($x);$ok=true;break;}}if(!$ok)return null;}return$t;}
 private function parseArray(){$end=null;if($this->take('['))$end=']';elseif($this->take('array')){if(!$this->take('('))throw new \UnexpectedValueException('Expected parenthesis');$end=')';}else throw new \UnexpectedValueException('Expected array');$r=array();if($this->take($end))return$r;while(true){$first=$this->value();if($this->take('=>'))$r[$first]=$this->value();else$r[]=$first;if($this->take($end))break;if(!$this->take(','))throw new \UnexpectedValueException('Expected comma');if($this->take($end))break;}return$r;}
 private function value(){if($this->peek('[')||$this->peek('array'))return$this->parseArray();if(!isset($this->tokens[$this->position])||$this->tokens[$this->position][0]!=='value')throw new \UnexpectedValueException('Expected literal');return$this->tokens[$this->position++][1];}
 private function peek($x){return isset($this->tokens[$this->position])&&$this->tokens[$this->position][0]==='symbol'&&$this->tokens[$this->position][1]===$x;}
 private function take($x){if($this->peek($x)){$this->position++;return true;}return false;}
}
