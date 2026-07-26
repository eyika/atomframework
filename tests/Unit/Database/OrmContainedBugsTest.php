<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Model;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Covers BUG-24 (typo `group_concact` + missing `bit_xor`), BUG-25 (findByEmail/
 * findByUsername whitelisted with a leading underscore → unreachable statically),
 * BUG-26 (restore() passed a positional list → wrote columns `0`/`1` and never
 * cleared deleted_at).
 */
class OrmContainedBugsTest extends TestCase
{
    public function test_dynamic_method_whitelist_is_corrected(): void
    {
        $methods = (new ReflectionClass(Model::class))->getConstant('DYNAMIC_STATIC_METHODS');

        $this->assertContains('bit_xor', $methods);
        $this->assertContains('group_concat', $methods);
        $this->assertContains('findByEmail', $methods);
        $this->assertContains('findByUsername', $methods);

        $this->assertNotContains('group_concact', $methods);   // typo gone
        $this->assertNotContains('_findByEmail', $methods);     // underscore-prefixed entry gone
    }

    public function test_restore_emits_associative_null_clause(): void
    {
        // restore() now passes ['deleted_at' => null]; values() must emit the real
        // column set to NULL, not positional `0`/`1` columns.
        $conn = new Connection([]);
        $values = new ReflectionMethod($conn, 'values');
        $values->setAccessible(true);

        $sql = $values->invoke($conn, ['deleted_at' => null]);

        $this->assertStringContainsString('`deleted_at` = NULL', $sql);
        $this->assertStringNotContainsString('`0`', $sql);
    }
}
