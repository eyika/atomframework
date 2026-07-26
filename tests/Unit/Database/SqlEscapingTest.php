<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers SEC-12..17: identifiers that can't be bound parameters (columns, tables,
 * ORDER BY targets, JOIN refs, JSON columns) must be escaped/whitelisted, never
 * interpolated raw. Tests the shared escapers + the JOIN/values sinks that use them.
 */
class SqlEscapingTest extends TestCase
{
    public function test_quote_ident_wraps_and_doubles_backticks(): void
    {
        $this->assertSame('`id`', Connection::quoteIdent('id'));
        // Injection payload is neutralised: the backtick is doubled and the whole
        // thing stays a single quoted identifier.
        $this->assertSame('`id``; DROP TABLE users;--`', Connection::quoteIdent('id`; DROP TABLE users;--'));
    }

    public function test_quote_qualified_handles_dot_notation(): void
    {
        $this->assertSame('`users`.`name`', Connection::quoteQualified('users.name'));
        $this->assertSame('`name`', Connection::quoteQualified('name'));
    }

    public function test_safe_join_type_whitelists(): void
    {
        $this->assertSame('LEFT', Connection::safeJoinType('left'));
        $this->assertSame('FULL OUTER', Connection::safeJoinType('full outer'));
        $this->assertSame('INNER', Connection::safeJoinType('LEFT JOIN x; DROP TABLE y'));
    }

    public function test_safe_comparator_whitelists(): void
    {
        $this->assertSame('=', Connection::safeComparator('='));
        $this->assertSame('>=', Connection::safeComparator('>='));
        $this->assertSame('=', Connection::safeComparator('= OR 1=1 --'));
    }

    public function test_compile_joins_escapes_identifiers_and_whitelists(): void
    {
        $conn = new Connection([]);
        $m = new ReflectionMethod($conn, 'compileJoins');
        $m->setAccessible(true);

        $sql = $m->invoke($conn, [[
            'type' => 'evil UNION',
            'table' => 'accounts; DROP TABLE users',
            'first' => 'a.id',
            'operator' => 'OR 1=1',
            'second' => 'b.account_id',
        ]]);

        $this->assertStringContainsString('INNER JOIN', $sql);                    // type fell back
        $this->assertStringContainsString('`accounts; DROP TABLE users`', $sql);  // table escaped (inert)
        $this->assertStringContainsString('`a`.`id`', $sql);
        $this->assertStringContainsString('`b`.`account_id`', $sql);
        $this->assertStringNotContainsString('OR 1=1', $sql);                     // operator whitelisted
    }

    public function test_values_escapes_column_identifiers(): void
    {
        $conn = new Connection([]);
        $m = new ReflectionMethod($conn, 'values');
        $m->setAccessible(true);

        $sql = $m->invoke($conn, ['name`--' => 'x']);
        $this->assertStringContainsString('`name``--`', $sql); // backtick doubled, escaped
    }

    public function test_values_rejects_malicious_json_path(): void
    {
        $conn = new Connection([]);
        $m = new ReflectionMethod($conn, 'values');
        $m->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $m->invoke($conn, ["meta.a'; DROP--" => 'x']);
    }
}
