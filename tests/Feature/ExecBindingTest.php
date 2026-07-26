<?php

namespace Eyika\Atom\Framework\Tests\Feature;

/**
 * Covers BUG-28: exec() now binds typed values with bindValue() + a no-arg
 * execute() (the old code passed $params to execute(), which discarded the typing,
 * and the bindParam-by-reference loop bound every param to the last value). Verifies
 * int/null/bool/string bind and round-trip correctly.
 */
class ExecBindingTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_typed');
        $this->raw('CREATE TABLE atomtest_typed (id INT PRIMARY KEY, score INT NULL, flag TINYINT NULL, label VARCHAR(20) NULL)');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_typed');
    }

    public function test_typed_params_bind_and_round_trip(): void
    {
        $this->connection->exec(
            'INSERT INTO atomtest_typed (id, score, flag, label) VALUES (:id, :score, :flag, :label)',
            [':id' => 7, ':score' => null, ':flag' => true, ':label' => 'seven']
        );

        $row = $this->connection->fetch('atomtest_typed', ['id' => 7])[0];

        $this->assertEquals(7, $row['id']);
        $this->assertNull($row['score']);      // NULL bound as PARAM_NULL
        $this->assertEquals(1, $row['flag']);  // bool true -> 1
        $this->assertSame('seven', $row['label']);
    }

    public function test_distinct_params_are_not_cross_bound(): void
    {
        // Two params with different values must keep their own values (the old
        // bindParam-by-reference loop bound them all to the last one).
        $this->connection->exec(
            'INSERT INTO atomtest_typed (id, score, label) VALUES (:id, :score, :label)',
            [':id' => 10, ':score' => 42, ':label' => 'ten']
        );

        $row = $this->connection->fetch('atomtest_typed', ['id' => 10])[0];

        $this->assertEquals(10, $row['id']);
        $this->assertEquals(42, $row['score']);
        $this->assertSame('ten', $row['label']);
    }

    public function test_get_surfaces_db_error_instead_of_swallowing(): void
    {
        // BUG-34: get() caught the PDOException and returned null with NO trace.
        $logFile = tempnam(sys_get_temp_dir(), 'atomlog');
        $orig = ini_get('error_log') ?: '';
        ini_set('error_log', $logFile);

        try {
            // _kv_atomtest_nospace does not exist -> the query throws -> get() now
            // logs it (and still returns null) rather than swallowing silently.
            $result = $this->connection->get('missing', 'atomtest_nospace');

            $this->assertNull($result);
            $this->assertStringContainsString('Atom DB get()', (string) file_get_contents($logFile));
        } finally {
            ini_set('error_log', $orig);
            @unlink($logFile);
        }
    }
}
