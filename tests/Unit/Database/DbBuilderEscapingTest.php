<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\DB;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * SEC-12/13/15 parity for the DB builder (sibling of QueryBuilder): its
 * orderBy/limit/offset/distinct must escape/whitelist identically, since its
 * ORDER BY/DISTINCT reach Connection's raw emission points.
 *
 * Query-state is now INSTANCE state (BUG-31), so we read the property off the
 * builder instance rather than statically.
 */
class DbBuilderEscapingTest extends TestCase
{
    private function prop(DB $builder, string $name): mixed
    {
        $p = new ReflectionProperty(DB::class, $name);
        $p->setAccessible(true);
        return $p->getValue($builder);
    }

    public function test_order_by_escapes_column_and_whitelists_direction(): void
    {
        $builder = (new DB())->orderBy('name; DROP TABLE users', 'evil');

        $order = ((array) $this->prop($builder, 'bind_or_filter'))['ORDER BY'];
        $this->assertStringContainsString('`name; DROP TABLE users`', $order);
        $this->assertStringEndsWith(' ASC', $order); // bad direction fell back to ASC
    }

    public function test_limit_and_offset_are_int_cast(): void
    {
        $builder = (new DB())->limit('10; DROP')->offset('5 UNION SELECT');

        $bof = (array) $this->prop($builder, 'bind_or_filter');
        $this->assertSame(10, $bof['LIMIT']);
        $this->assertSame(5, $bof['OFFSET']);
    }

    public function test_distinct_escapes_column(): void
    {
        $builder = (new DB())->distinct('col`--');

        $ops = $this->prop($builder, 'operators');
        $joined = is_array($ops) ? implode(' ', $ops) : (string) $ops;
        $this->assertStringContainsString('`col``--`', $joined);
    }
}
