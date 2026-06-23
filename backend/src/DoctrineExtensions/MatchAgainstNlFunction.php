<?php
namespace App\DoctrineExtensions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;

/**
 * MATCH_AGAINST_NL(col1, col2, :needle)
 * → MATCH(col1, col2) AGAINST (:needle IN NATURAL LANGUAGE MODE)
 */
final class MatchAgainstNlFunction extends FunctionNode
{
    /** @var array<StringPrimary> */
    private array $columns = [];
    /** @var StringPrimary */
    private $needle;

    public function parse(\Doctrine\ORM\Query\Parser $parser): void
    {
        // MATCH_AGAINST_NL (
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        // собираем ВСЕ аргументы через запятую
        $args   = [];
        $args[] = $parser->StringPrimary();
        while ($parser->getLexer()->isNextToken(Lexer::T_COMMA)) {
            $parser->match(Lexer::T_COMMA);
            $args[] = $parser->StringPrimary();
        }
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);

        // последний аргумент — needle, остальные — колонки
        $this->needle  = array_pop($args);
        $this->columns = $args;
    }

    public function getSql(\Doctrine\ORM\Query\SqlWalker $walker): string
    {
        $cols = array_map(
            static fn($expr) => $expr->dispatch($walker),
            $this->columns
        );

        return sprintf(
            'MATCH(%s) AGAINST (%s IN NATURAL LANGUAGE MODE)',
            implode(', ', $cols),
            $this->needle->dispatch($walker)
        );
    }
}
