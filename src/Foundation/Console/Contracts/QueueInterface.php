<?php
namespace Eyika\Atom\Framework\Foundation\Console\Contracts;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
use PDO;
use SQLite3;

interface QueueInterface
{
    
    /**
     * handles the queue logic
     * 
     * @return void
     */
    public function handle();

    public function setJob(array $job): void;

    public function setQueue(Job_Queue $queue): void;

    public function init (): self;

    public function delay(int $delay): self;

    // public function fail();

    // public function delete(): void;

    public function priority(int $prio): self;

    public static function dispatch(): self;

    public function onQueue(string $pipeline_name): self;

    public function onConnection(PDO|SQLite3 $connection): self;

    public function run(): void;
}