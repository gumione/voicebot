<?php
namespace App\DoctrineExtensions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;

final class MatchAgainstFunction extends FunctionNode
{
    /** @var array<StringPrimary> */
    private array $columns = [];

    /** @var StringPrimary */
    private $needle;

    public function parse(\Doctrine\ORM\Query\Parser $parser): void
    {
        // MATCH_AGAINST(
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        // считываем список аргументов до ')'
        $exprs   = [];
        $exprs[] = $parser->StringPrimary();
        while ($parser->getLexer()->isNextToken(Lexer::T_COMMA)) {
            $parser->match(Lexer::T_COMMA);
            $exprs[] = $parser->StringPrimary();
        }
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);

        // последний аргумент — строка поиска, остальные — колонки
        $this->needle  = array_pop($exprs);
        $this->columns = $exprs;
    }

    public function getSql(\Doctrine\ORM\Query\SqlWalker $walker): string
    {
        $cols = array_map(
            static fn($expr) => $expr->dispatch($walker),
            $this->columns
        );

        return sprintf(
            'MATCH(%s) AGAINST (%s IN BOOLEAN MODE)',
            implode(', ', $cols),
            $this->needle->dispatch($walker)
        );
    }
}
