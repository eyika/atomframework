<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude C (vendra): the Validator handled dot notation for a FIXED path but had no
 * `items.*.name` form, so a repeated line-item payload (invoice lines) could not be validated
 * declaratively — `'items' => 'required|array'` was as far as it went, and each element then had
 * to be checked by hand in the controller.
 *
 * Wildcard keys are expanded against the payload before any rule runs, so the existing rule set
 * and dot-notation handling work unchanged.
 */
class ValidatorWildcardTest extends TestCase
{
    private function payload(): array
    {
        return [
            'items' => [
                ['name' => 'Widget', 'qty' => 2],
                ['name' => 'Gadget', 'qty' => 5],
            ],
        ];
    }

    public function test_wildcard_passes_when_every_element_is_valid(): void
    {
        $result = Validator::validate($this->payload(), [
            'items'        => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.qty'  => 'required|integer',
        ]);

        $this->assertNotFalse($result, 'a fully valid collection should pass');
    }

    public function test_wildcard_reports_the_offending_element_by_index(): void
    {
        $payload = $this->payload();
        $payload['items'][1]['name'] = '';   // second line item is invalid

        $result = Validator::validate($payload, [
            'items.*.name' => 'required|string',
        ]);

        $this->assertFalse($result);

        $errors = Validator::$errors;
        $this->assertArrayHasKey('items.1.name', $errors, 'the failing index should be identified');
        $this->assertArrayNotHasKey('items.0.name', $errors, 'the valid element must not error');
    }

    public function test_wildcard_validates_every_element_not_just_the_first(): void
    {
        $payload = ['items' => [
            ['qty' => 1],
            ['qty' => 'not-a-number'],
            ['qty' => 3],
        ]];

        $result = Validator::validate($payload, ['items.*.qty' => 'required|integer']);

        $this->assertFalse($result);
        $this->assertArrayHasKey('items.1.qty', Validator::$errors);
    }

    public function test_nested_wildcards_resolve(): void
    {
        $payload = ['orders' => [
            ['lines' => [['sku' => 'A'], ['sku' => '']]],
        ]];

        $result = Validator::validate($payload, ['orders.*.lines.*.sku' => 'required|string']);

        $this->assertFalse($result);
        $this->assertArrayHasKey('orders.0.lines.1.sku', Validator::$errors);
    }

    /**
     * A per-element rule cannot assert the container exists — with no items there are no
     * elements to validate. Requiring the collection is the container rule's job.
     */
    public function test_wildcard_over_a_missing_collection_expands_to_nothing(): void
    {
        $this->assertNotFalse(
            Validator::validate([], ['items.*.name' => 'required|string'])
        );

        $this->assertFalse(
            Validator::validate([], ['items' => 'required|array', 'items.*.name' => 'required|string'])
        );
    }

    public function test_non_wildcard_rules_are_unaffected(): void
    {
        $result = Validator::validate(
            ['email' => 'someone@example.com', 'nested' => ['field' => 'value']],
            ['email' => 'required|email', 'nested.field' => 'required|string']
        );

        $this->assertNotFalse($result);
    }
}
