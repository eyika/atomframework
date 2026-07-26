<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Auth\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Safety net for the __callStatic/__call convention: EVERY name in
 * DYNAMIC_STATIC_METHODS must bind to a real method, or it 500s at runtime with
 * "Method does not exist". __callStatic resolves `$name` to `_{$name}` when that
 * exists, else `$name`. User uses both QueryBuilder (base) and UserAwareQueryBuilder
 * (_findByEmail/_findByUsername), so it's the fully-featured target to check against.
 */
class DynamicMethodResolutionTest extends TestCase
{
    public function test_every_whitelisted_dynamic_method_binds_to_a_real_method(): void
    {
        $rc = new ReflectionClass(User::class);
        $whitelist = $rc->getConstant('DYNAMIC_STATIC_METHODS');
        $this->assertNotEmpty($whitelist);

        $unresolved = [];
        foreach ($whitelist as $name) {
            // Mirror __callStatic: resolves iff _{name} or {name} exists.
            if (!$rc->hasMethod('_' . $name) && !$rc->hasMethod($name)) {
                $unresolved[] = $name;
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            'Whitelisted dynamic methods with NO backing method (would 500 at runtime): ' . implode(', ', $unresolved)
        );
    }

    public function test_finders_resolve_to_user_aware_methods(): void
    {
        $rc = new ReflectionClass(User::class);

        // findByEmail/findByUsername → _findByEmail/_findByUsername (single underscore).
        $this->assertTrue($rc->hasMethod('_findByEmail'));
        $this->assertTrue($rc->hasMethod('_findByUsername'));
    }
}
