<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Support\Database\Connection;

/**
 * Builds the job queue on the application's own database connection.
 *
 * One place, because there were three, and they disagreed. `ShouldQueue::dispatch()` and
 * `ShouldQueue::init()` hand-assembled `"mysql:dbname=…;host=…"` out of `env()`, and
 * `JobRunner::makeQueue()` hand-assembled the same string out of `config()`. None of the three
 * wrote a port, so an engine on anything but 3306 was silently not the database the application
 * itself was using — and with a second server beside an older one, that means the new credentials
 * presented to the old host, which answers "access denied" and reads like a password problem.
 *
 * They also dropped the charset and every configured PDO option, `ERRMODE_EXCEPTION` included, so
 * a queue write that failed did so in silence.
 *
 * The connection is now whichever one `database.default` names, so the queue follows the database
 * the application is actually using rather than assuming MySQL. `Job_Queue` handles the dialect
 * from there — see its `sqlDriver()`, which asks the handle rather than trusting a label.
 */
final class QueueConnector
{
    /** The table jobs are stored in. */
    public const JOBS_TABLE = 'jobs';

    /** The table jobs are moved to once they have failed for good. */
    public const FAILED_JOBS_TABLE = 'failed_jobs';

    /**
     * The name of the configured connection the queue runs on.
     *
     * `database.default` holds a CONNECTION name, which is not necessarily a driver name — an app
     * may call its Postgres connection "primary". The driver is read off the connection itself.
     */
    public static function connectionName(): string
    {
        return (string) config('database.default', 'mysql');
    }

    /** The driver of that connection, which is what decides the queue's dialect. */
    public static function driver(): string
    {
        $connection = self::connectionName();

        return (string) config("database.connections.{$connection}.driver", $connection);
    }

    /**
     * A queue bound to the application's database, with no pipeline selected yet.
     *
     * The caller chooses `setPipeline()`/`selectPipeline()` (dispatching) or `watchPipeline()`
     * (working), because those mean different things to a queue daemon even though they are both
     * no-ops for a SQL-backed one.
     */
    public static function make(): Job_Queue
    {
        $driver = self::driver();

        // Compression is MySQL's COMPRESS()/UNCOMPRESS() and exists nowhere else; Job_Queue gates
        // it on the real driver, so leaving it on here keeps MySQL's long-standing default —
        // which must not change under an existing queue, since a payload written compressed is
        // readable only through UNCOMPRESS().
        $queue = new Job_Queue($driver, [
            $driver => [
                'table_name' => self::JOBS_TABLE,
                'failed_table_name' => self::FAILED_JOBS_TABLE,
                'use_compression' => true,
            ],
        ]);

        $queue->addQueueConnection(Connection::makePdo(config('database'), self::connectionName()));

        return $queue;
    }
}
