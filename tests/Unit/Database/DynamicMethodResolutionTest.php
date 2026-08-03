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

    /**
     * The REVERSE direction of the test above, and the one that was missing: every `_`-prefixed
     * public builder method must appear in the whitelist. Without it the list drifts silently —
     * reported by Claude A when `Model::orderBy(...)` blew up after fx-data-server dropped ~331
     * redundant `getBuilder()` hops.
     *
     * The invariant is deliberately mechanical — whitelist == set of `_foo` methods — so it can
     * be checked rather than curated. Note a plain `public function foo()` CANNOT be fixed by
     * adding it here: PHP resolves a real public method directly and raises "Non-static method
     * … cannot be called statically" before __callStatic runs. Such a method must be renamed
     * `_foo` first, which is why orderBy/with/raw were renamed rather than just listed.
     */
    public function test_every_underscored_builder_method_is_whitelisted(): void
    {
        $rc = new ReflectionClass(User::class);
        $whitelist = $rc->getConstant('DYNAMIC_STATIC_METHODS');

        $missing = [];
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            $name = $m->getName();

            if (!str_starts_with($name, '_') || str_starts_with($name, '__')) {
                continue;
            }

            $exposed = substr($name, 1);

            if (!in_array($exposed, $whitelist, true)) {
                $missing[] = $exposed;
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            'Builder methods missing from DYNAMIC_STATIC_METHODS (not callable as Model::x()): '
                . implode(', ', $missing)
        );
    }

    /**
     * Standing rule 1 (contracts stay in sync), enforced rather than remembered: every
     * `_`-prefixed builder method must also be declared on ModelInterface. Found drifting at the
     * same time as the whitelist — `_orWhereIn`, `_orWhereNotIn`, `_firstOr` and `_lockForUpdate`
     * were implemented but undeclared.
     */
    public function test_every_underscored_builder_method_is_declared_on_the_contract(): void
    {
        $trait = new ReflectionClass(\Eyika\Atom\Framework\Support\Database\Concerns\QueryBuilder::class);
        $iface = new ReflectionClass(\Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface::class);

        $declared = array_map(fn ($m) => $m->getName(), $iface->getMethods());

        $undeclared = [];
        foreach ($trait->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            $name = $m->getName();

            if (!str_starts_with($name, '_') || str_starts_with($name, '__')) {
                continue;
            }

            if (!in_array($name, $declared, true)) {
                $undeclared[] = $name;
            }
        }

        sort($undeclared);

        $this->assertSame(
            [],
            $undeclared,
            'Builder methods missing from ModelInterface: ' . implode(', ', $undeclared)
        );
    }

    /** A duplicated entry is harmless at runtime but means the list is being edited blind. */
    public function test_the_whitelist_has_no_duplicates(): void
    {
        $whitelist = (new ReflectionClass(User::class))->getConstant('DYNAMIC_STATIC_METHODS');

        $dupes = array_keys(array_filter(array_count_values($whitelist), fn ($n) => $n > 1));

        $this->assertSame([], $dupes, 'duplicated whitelist entries: ' . implode(', ', $dupes));
    }

    /**
     * Guards the failure mode the whitelist alone cannot express: a plain public method is
     * unreachable statically no matter what the list says. If any of these ever loses its
     * underscore, `Model::orderBy(...)` starts raising a raw PHP Error again.
     */
    public function test_chainable_entry_points_are_underscore_prefixed(): void
    {
        $rc = new ReflectionClass(User::class);

        foreach (['orderBy', 'with', 'raw'] as $name) {
            $this->assertTrue(
                $rc->hasMethod('_' . $name),
                "_{$name}() must exist — a plain public {$name}() cannot be called statically"
            );
            $this->assertFalse(
                $rc->hasMethod($name),
                "a plain public {$name}() shadows the dynamic dispatch and breaks Model::{$name}()"
            );
        }
    }

    public function test_finders_resolve_to_user_aware_methods(): void
    {
        $rc = new ReflectionClass(User::class);

        // findByEmail/findByUsername → _findByEmail/_findByUsername (single underscore).
        $this->assertTrue($rc->hasMethod('_findByEmail'));
        $this->assertTrue($rc->hasMethod('_findByUsername'));
    }
}
