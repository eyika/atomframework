<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
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

    public static function dispatch (): self
    {
        $me = new self;
        $me::$queue = new Job_Queue('mysql', [
            'mysql' => [
                'table_name' => 'jobs',     //the table that jobs will be stored in
                'use_compression' => true
            ]
        ]);

        $db_name = env('DB_DATABASE');
        $db_host = env('DB_HOST');

        $pdo = new PDO("mysql:dbname=$db_name;host=$db_host", env('DB_USERNAME'), env('DB_PASSWORD'));
        $me::$queue->addQueueConnection($pdo);
        $me::$queue->setPipeline('default');
        $me::$queue->selectPipeline('default');

        return $me;
    }

    public function init (): self
    {
        $this::$queue = new Job_Queue('mysql', [
            'mysql' => [
                'table_name' => 'jobs',     //the table that jobs will be stored in
                'use_compression' => true
            ]
        ]);
        $db_name = env('DB_DATABASE');
        $db_host = env('DB_HOST');

        $pdo = new PDO("mysql:dbname=$db_name;host=$db_host", env('DB_USERNAME'), env('DB_PASSWORD'));
        $this::$queue->addQueueConnection($pdo);
        $this::$queue->setPipeline('default');
        $this::$queue->selectPipeline('default');

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
     * @param int $delay
     * @return void
     */
    private function bury(int $delay = null)
    {
        $id = $this->job['id'];
        unset($this->job);
        $sclass = serialize($this);
        $this->delay = $delay ?? $this->delay;
        return $this::$queue->buryJob(['payload' => $sclass, 'id' => $id], $this->delay);
    }

    public function run(): void
    {
        $sclass = serialize($this);
        $this::$queue->addJob($sclass, $this->delay, $this->priority, $this->delay);
    }
}
