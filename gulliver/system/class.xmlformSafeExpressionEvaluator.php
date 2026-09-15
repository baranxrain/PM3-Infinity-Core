<?php

/**
 * Evaluates the literal expression subset used by legacy CellMark conditions.
 * Unsupported syntax fails closed instead of executing PHP source.
 */
final class XmlFormSafeExpressionEvaluator
{
    private $tokens = array();
    private $position = 0;

    public static function evaluate($expression)
    {
        try {
            $tokens = self::tokenize((string) $expression);
            if ($tokens === null || $tokens === array()) {
                return false;
            }
            $parser = new self($tokens);
            $value = $parser->parseOr();
            if ($parser->position !== count($parser->tokens)) {
                return false;
            }
            return (bool) $value;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    private static function tokenize($expression)
    {
        $tokens = array();
        $length = strlen($expression);
        $position = 0;
        $operators = array('===', '!==', '==', '!=', '<=', '>=', '&&', '||', '(', ')', '!', '<', '>', '+', '-', '*', '/', '%', '.');
        while ($position < $length) {
            if (preg_match('/\G\s+/A', $expression, $match, 0, $position)) {
                $position += strlen($match[0]);
                continue;
            }
            $character = $expression[$position];
            if ($character === "'" || $character === '"') {
                $quote = $character;
                $position++;
                $value = '';
                $closed = false;
                while ($position < $length) {
                    $character = $expression[$position++];
                    if ($character === $quote) {
                        $closed = true;
                        break;
                    }
                    if ($character === '\\' && $position < $length) {
                        $next = $expression[$position++];
                        if ($quote === "'") {
                            $value .= ($next === "'" || $next === '\\') ? $next : '\\' . $next;
                        } else {
                            $value .= stripcslashes('\\' . $next);
                        }
                    } else {
                        $value .= $character;
                    }
                }
                if (!$closed) {
                    return null;
                }
                $tokens[] = array('value', $value);
                continue;
            }
            if (preg_match('/\G(?:\d+\.\d+|\d+)/A', $expression, $match, 0, $position)) {
                $text = $match[0];
                $tokens[] = array('value', strpos($text, '.') === false ? (int) $text : (float) $text);
                $position += strlen($text);
                continue;
            }
            if (preg_match('/\G(?:true|false|null|and|or|xor)\b/Ai', $expression, $match, 0, $position)) {
                $word = strtolower($match[0]);
                if ($word === 'true' || $word === 'false' || $word === 'null') {
                    $tokens[] = array('value', $word === 'true' ? true : ($word === 'false' ? false : null));
                } else {
                    $tokens[] = array('operator', $word);
                }
                $position += strlen($match[0]);
                continue;
            }
            $matched = false;
            foreach ($operators as $operator) {
                if (substr($expression, $position, strlen($operator)) === $operator) {
                    $tokens[] = array('operator', $operator);
                    $position += strlen($operator);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return null;
            }
        }
        return $tokens;
    }

    private function parseOr()
    {
        $value = $this->parseXor();
        while ($this->matches('||') || $this->matches('or')) {
            $right = $this->parseXor();
            $value = (bool) $value || (bool) $right;
        }
        return $value;
    }

    private function parseXor()
    {
        $value = $this->parseAnd();
        while ($this->matches('xor')) {
            $right = $this->parseAnd();
            $value = ((bool) $value xor (bool) $right);
        }
        return $value;
    }

    private function parseAnd()
    {
        $value = $this->parseComparison();
        while ($this->matches('&&') || $this->matches('and')) {
            $right = $this->parseComparison();
            $value = (bool) $value && (bool) $right;
        }
        return $value;
    }

    private function parseComparison()
    {
        $left = $this->parseConcatenation();
        $operator = $this->matchOne(array('===', '!==', '==', '!=', '<=', '>=', '<', '>'));
        if ($operator === null) {
            return $left;
        }
        $right = $this->parseConcatenation();
        switch ($operator) {
            case '===': return $left === $right;
            case '!==': return $left !== $right;
            case '==': return $left == $right;
            case '!=': return $left != $right;
            case '<=': return $left <= $right;
            case '>=': return $left >= $right;
            case '<': return $left < $right;
            case '>': return $left > $right;
        }
        return false;
    }

    private function parseConcatenation()
    {
        $value = $this->parseAdditive();
        while ($this->matches('.')) {
            $value = (string) $value . (string) $this->parseAdditive();
        }
        return $value;
    }

    private function parseAdditive()
    {
        $value = $this->parseMultiplicative();
        while (($operator = $this->matchOne(array('+', '-'))) !== null) {
            $right = $this->parseMultiplicative();
            $value = $operator === '+' ? $value + $right : $value - $right;
        }
        return $value;
    }

    private function parseMultiplicative()
    {
        $value = $this->parseUnary();
        while (($operator = $this->matchOne(array('*', '/', '%'))) !== null) {
            $right = $this->parseUnary();
            if (($operator === '/' || $operator === '%') && (float) $right == 0.0) {
                throw new UnexpectedValueException('Division by zero');
            }
            if ($operator === '*') { $value *= $right; }
            elseif ($operator === '/') { $value /= $right; }
            else { $value %= $right; }
        }
        return $value;
    }

    private function parseUnary()
    {
        if ($this->matches('!')) { return !$this->parseUnary(); }
        if ($this->matches('+')) { return +$this->parseUnary(); }
        if ($this->matches('-')) { return -$this->parseUnary(); }
        return $this->parsePrimary();
    }

    private function parsePrimary()
    {
        if ($this->matches('(')) {
            $value = $this->parseOr();
            if (!$this->matches(')')) { throw new UnexpectedValueException('Missing parenthesis'); }
            return $value;
        }
        if (!isset($this->tokens[$this->position]) || $this->tokens[$this->position][0] !== 'value') {
            throw new UnexpectedValueException('Expected literal');
        }
        return $this->tokens[$this->position++][1];
    }

    private function matches($operator)
    {
        if (isset($this->tokens[$this->position]) && $this->tokens[$this->position][0] === 'operator' && $this->tokens[$this->position][1] === $operator) {
            $this->position++;
            return true;
        }
        return false;
    }

    private function matchOne(array $operators)
    {
        foreach ($operators as $operator) {
            if ($this->matches($operator)) { return $operator; }
        }
        return null;
    }
}
