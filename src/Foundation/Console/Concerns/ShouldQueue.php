<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
use Eyika\Atom\Framework\Foundation\Console\QueueConnector;
use PDO;
use SQLite3;

trait ShouldQueue
{
    private $delay = 60;

    private $priority = 1024;

    private array $job;

    private static Job_Queue $queue;

    public function setJob(array $job): void
    {
        $this->job = $job;
    }

    public function setQueue(Job_Queue $queue): void
    {
        $this::$queue = $queue;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Build the queue on the application's CONFIGURED database connection.
     *
     * This used to hand-assemble `"mysql:dbname=…;host=…"` from `env()`, which was wrong twice
     * over. It dropped the port, so a database on anything but 3306 was silently the wrong server —
     * and with a second engine running beside an old one, "wrong server" means the new credentials
     * presented to the old host, i.e. an access-denied that looks like a password problem and is
     * not. It also dropped the charset and every configured PDO option, including
     * `ERRMODE_EXCEPTION`, so a queue write that failed did so in silence.
     *
     * Reading `env()` rather than `config()` was the deeper error: `env()` sees `$_ENV` only, and
     * php.ini ships `variables_order="GPCS"`, so an exported shell variable never arrives. An app
     * that configures its database in `config/database.php` without a `.env` got a DSN of empty
     * strings and connected to whatever localhost default PDO chose.
     *
     * `Connection::makePdo()` builds the handle exactly as the ORM builds its own, from the same
     * config, so the dispatcher and the worker cannot disagree about where the queue lives.
     */
    private static function makeQueue(): Job_Queue
    {
        $queue = QueueConnector::make();
        $queue->setPipeline('default');
        $queue->selectPipeline('default');

        return $queue;
    }

    public static function dispatch (): self
    {
        $me = new self;
        $me::$queue = self::makeQueue();

        return $me;
    }

    public function init (): self
    {
        $this::$queue = self::makeQueue();

        return $this;
    }

    public function delay(int $delay): self
    {
        $this->delay = $delay;
        return $this;
    }

    public function priority(int $prio): self
    {
        $this->priority = $prio;
        return $this;
    }

    public function onQueue(string $pipeline_name): self
    {
        $this::$queue->setPipeline($pipeline_name);
        return $this;
    }

    public function onConnection(PDO|SQLite3 $connection): self
    {
        $this::$queue->addQueueConnection($connection);
        return $this;
    }

    private function fail()
    {
        $this::$queue->failJob($this->job);
        return $this::$queue->deleteJob($this->job);
    }

    private function delete()
    {
        return $this::$queue->deleteJob($this->job);
    }

    /**
     * Release the job to a different Queue
     * 
     * @param int $delay in minutes
     * @return void
     */
    private function bury(int|null $delay = null)
    {
        $id = $this->job['id'];
        unset($this->job);
        $sclass = \Eyika\Atom\Framework\Support\SignedPayload::sign($this);
        $this->delay = $delay ?? $this->delay;
        return $this::$queue->buryJob(['payload' => $sclass, 'id' => $id], $this->delay);
    }

    public function run(): void
    {
        $sclass = \Eyika\Atom\Framework\Support\SignedPayload::sign($this);
        $this::$queue->addJob($sclass, $this->delay, $this->priority, $this->delay);
    }
}
