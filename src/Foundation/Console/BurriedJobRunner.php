<?php
namespace Eyika\Atom\Framework\Foundation\Console;

use Dotenv\Dotenv;
use Exception;
use Eyika\Atom\Framework\Foundation\Console\Contracts\QueueInterface;
use PDO;

class BurriedJobRunner
{
    public function __invoke()
    {
        $dotenv = Dotenv::createImmutable(__DIR__."/../../");
        $dotenv->safeLoad();

        $dotenv->required(['DB_ADAPTER', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT', 'DB_CHARSET', 'CHRON_INTERVAL'])->notEmpty();

        $dbtype = env('DB_ADAPTER');
        $dbhost = env("DB_HOST");
        $dbname = env('DB_NAME');
        $dbuser = env('DB_USER');
        $dbpass = env('DB_PASS');
        $dbport = env('DB_PORT');
        $dbcharset = env('DB_CHARSET');
        $chron_interval = env('CHRON_INTERVAL');

        $start_time = time();
        $end_time = $start_time + $chron_interval;

        $job_Queue = new Job_Queue(Job_Queue::QUEUE_TYPE_MYSQL, [
            $dbtype => [
                'table_name' => 'jobs',     //the table that jobs will be stored in
                'use_compression' => true
            ]
        ]);

        $pdo = new PDO("$dbtype:dbname=$dbname;host=$dbhost", $dbuser, $dbpass);
        $job_Queue->addQueueConnection($pdo);
        $job_Queue->watchPipeline('default');

        while ($end_time > time()) {
            $job = $job_Queue->getNextBuriedJob();
            if (empty($job)) {
                sleep(1);
                continue;
            }

            $payload = $job['payload'];

            try {
                $job_obj = unserialize($payload);

                if ($job_obj instanceof QueueInterface) {
                    $job_obj->setJob($job);
                    $job_obj->setQueue($job_Queue);
                    $resp = $job_obj->handle();
                }
            } catch (Exception $e) {
                $job_Queue->buryJob($job, $job_obj->getDelay());
            }
        }
    }
}
