<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Covers BUG-46 (integer accepted "1e3"), BUG-44 (in/not_in mishandled array/typed
 * values), BUG-45 (mimes fatalled on a non-File value). validate() returns the
 * validated array on success, false on failure.
 */
class ValidatorTest extends TestCase
{
    public function test_integer_rejects_scientific_and_decimal(): void
    {
        $this->assertFalse(Validator::validate(['n' => '1e3'], ['n' => 'integer']));
        $this->assertFalse(Validator::validate(['n' => '10.5'], ['n' => 'integer']));
        $this->assertNotFalse(Validator::validate(['n' => '10'], ['n' => 'integer']));
        $this->assertNotFalse(Validator::validate(['n' => 42], ['n' => 'integer']));
    }

    public function test_in_rule_accepts_and_rejects(): void
    {
        $this->assertNotFalse(Validator::validate(['c' => 'red'], ['c' => 'in:red,green']));
        $this->assertFalse(Validator::validate(['c' => 'blue'], ['c' => 'in:red,green']));
    }

    public function test_in_rule_matches_numeric_as_string(): void
    {
        $this->assertNotFalse(Validator::validate(['n' => 5], ['n' => 'in:5,6,7']));
    }

    public function test_in_rule_rejects_an_array_value(): void
    {
        // An array can't be "one of" the scalar options.
        $this->assertFalse(Validator::validate(['c' => ['red']], ['c' => 'in:red,green']));
    }

    public function test_mimes_on_non_file_is_an_error_not_a_fatal(): void
    {
        // A string where a file is expected must produce a validation error, not a
        // fatal ->uploadProperties() call on a non-File value.
        $this->assertFalse(Validator::validate(['f' => 'not-a-file'], ['f' => 'mimes:png,jpg']));
    }
}
