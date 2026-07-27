<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;

/**
 * Covers PERF-09: fetch() decodes columns whose name contains '_json' (strpos > 0).
 * The scan is now precomputed once per result set instead of per column per row;
 * this asserts the decoding behavior is unchanged.
 */
class JsonColumnDecodeTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_json');
        $this->raw('CREATE TABLE atomtest_json (id INT PRIMARY KEY, name VARCHAR(30), meta_json TEXT)');
        $this->raw('INSERT INTO atomtest_json (id, name, meta_json) VALUES '
            . "(1,'a','{\"k\":1,\"tags\":[\"x\",\"y\"]}'),"
            . "(2,'b','{\"k\":2,\"tags\":[]}')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_json');
    }

    public function test_json_named_column_is_decoded_to_array(): void
    {
        $row = DB::table('atomtest_json')->where('id', 1)->first();

        $this->assertIsArray($row['meta_json']);
        $this->assertSame(1, $row['meta_json']['k']);
        $this->assertSame(['x', 'y'], $row['meta_json']['tags']);

        // A non-_json column is returned untouched.
        $this->assertSame('a', $row['name']);
    }

    public function test_decoding_applies_across_all_rows(): void
    {
        $rows = DB::table('atomtest_json')->all();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertIsArray($row['meta_json']);
            $this->assertArrayHasKey('tags', $row['meta_json']);
        }
    }
}
