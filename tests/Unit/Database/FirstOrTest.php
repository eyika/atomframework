<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Model;
use PHPUnit\Framework\TestCase;

/**
 * Covers the newly-implemented firstOr(): returns the first match, or the result of
 * the callback when none is found. Uses a Model stub that overrides first() so the
 * logic is exercised without a database.
 */
class FirstOrTest extends TestCase
{
    private function stub(mixed $firstReturn): Model
    {
        $model = new class extends Model {
            public $table = 'stub';
            public mixed $stubFirst = false;
            public function first($is_protected = true): mixed
            {
                return $this->stubFirst;
            }
        };
        $model->stubFirst = $firstReturn;

        return $model;
    }

    public function test_returns_the_model_when_found(): void
    {
        $model = $this->stub('the-model');

        $this->assertSame('the-model', $model->_firstOr(fn() => 'fallback'));
    }

    public function test_runs_callback_when_missing(): void
    {
        $model = $this->stub(false);

        $this->assertSame('fallback', $model->_firstOr(fn() => 'fallback'));
    }

    public function test_returns_first_result_without_calling_callback_when_present(): void
    {
        $called = false;
        $model = $this->stub('present');

        $model->_firstOr(function () use (&$called) {
            $called = true;
            return 'fallback';
        });

        $this->assertFalse($called);
    }
}
