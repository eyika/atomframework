<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Grammars\MySqlGrammar;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Regression: the SELECT column list must quote identifiers so a column named with a SQL reserved
 * word (e.g. `values`, `order`) doesn't emit invalid SQL. The model's post-insert reload builds the
 * list from `fillable`; before the fix it was imploded unquoted, so a `values` column produced a
 * 1064 syntax error (and an empty list produced `SELECT  FROM`). `*`, expressions, aliases and
 * already-quoted names pass through; an empty list falls back to `*`.
 */
class SelectListQuotingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Pin the grammar so backtick expectations are deterministic regardless of suite order.
        $p = new ReflectionProperty(Connection::class, 'activeGrammar');
        $p->setAccessible(true);
        $p->setValue(null, new MySqlGrammar());
    }

    public function test_reserved_word_column_is_quoted(): void
    {
        $this->assertSame('`id`, `values`', Connection::compileSelectList(['id', 'values']));
    }

    public function test_qualified_column_is_quoted_per_part(): void
    {
        $this->assertSame('`product_options`.`values`', Connection::compileSelectList(['product_options.values']));
    }

    public function test_star_and_expressions_pass_through(): void
    {
        $this->assertSame('*', Connection::compileSelectList('*'));
        $this->assertSame('*', Connection::compileSelectList(['*']));
        $this->assertSame('count(*)', Connection::compileSelectList('count(*)'));       // verbatim string
        $this->assertSame('`id`, count(*)', Connection::compileSelectList(['id', 'count(*)']));
        $this->assertSame('name as alias', Connection::compileSelectList(['name as alias']));
    }

    public function test_empty_list_falls_back_to_star(): void
    {
        $this->assertSame('*', Connection::compileSelectList([]));
        $this->assertSame('*', Connection::compileSelectList(['']));
    }
}
