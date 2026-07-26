<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Session\MysqlSessionHandler;
use ReflectionProperty;

/**
 * Covers BUG-54 (write() left :session_data unbound → payload never persisted),
 * BUG-55 (gc() left :max_lifetime unbound → always errored + retried forever), and
 * the read() shape bug (returned the row array, not the session_data string).
 */
class SessionHandlerTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_sessions');
        $this->raw('CREATE TABLE atomtest_sessions (id VARCHAR(64) PRIMARY KEY, session_data BLOB, session_last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_sessions');
    }

    private function handler(): MysqlSessionHandler
    {
        $handler = new MysqlSessionHandler();
        $table = new ReflectionProperty($handler, 'table');
        $table->setAccessible(true);
        $table->setValue($handler, 'atomtest_sessions');

        return $handler;
    }

    public function test_write_then_read_round_trips(): void
    {
        $handler = $this->handler();

        $this->assertTrue($handler->write('sess-1', 'hello-session'));
        $this->assertSame('hello-session', $handler->read('sess-1'));
    }

    public function test_read_missing_session_returns_empty_string(): void
    {
        $this->assertSame('', $this->handler()->read('nope'));
    }

    public function test_gc_deletes_expired_rows_and_returns_count(): void
    {
        $this->raw("INSERT INTO atomtest_sessions (id, session_data, session_last_updated) VALUES ('old', 'x', DATE_SUB(NOW(), INTERVAL 1 DAY))");
        $this->raw("INSERT INTO atomtest_sessions (id, session_data, session_last_updated) VALUES ('fresh', 'y', NOW())");

        $deleted = $this->handler()->gc(60); // older than 60s

        $this->assertSame(1, $deleted);
        $this->assertFalse(DB::table('atomtest_sessions')->where('id', 'old')->first());
        $this->assertNotFalse(DB::table('atomtest_sessions')->where('id', 'fresh')->first());
    }
}
