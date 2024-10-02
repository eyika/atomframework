<?php
namespace Eyika\Atom\Framework\Foundation\Console;

use Dotenv\Dotenv;
use Exception;
use Eyika\Atom\Framework\Foundation\Console\Contracts\QueueInterface;
use PDO;

class JobRunner {
    public function __invoke()
    {
        //TODO: there should be db abstraction so we can be db agnostic
        $dbtype = config('database.connections.mysql.driver'); // env('DB_ADAPTER');
        $dbhost = config('database.connections.mysql.host'); //env("DB_HOST");
        $dbname = config('database.connections.mysql.database'); //env('DB_NAME');
        $dbuser = config('database.connections.mysql.username'); //env('DB_USER');
        $dbpass = config('database.connections.mysql.password'); //env('DB_PASS');
        $dbport = config('database.connections.mysql.port'); //env('DB_PORT');
        $dbcharset = config('database.connections.mysql.charset'); //env('DB_CHARSET');
        $chron_interval = (int)env('CHRON_INTERVAL', 60);

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
            // Process Pending Jobs
            $job = $job_Queue->getNextJobAndReserve();
            
            if(!empty($job)) {
                $payload = $job['payload'];
    
                try {
                    $job_obj = unserialize($payload);
    
                    if ($job_obj instanceof QueueInterface) {
                        $job_obj->setJob($job);
                        $job_obj->setQueue($job_Queue);
                        $resp = $job_obj->handle();
                    }
                } catch(Exception $e) {
                    $job_Queue->buryJob($job, $job_obj->getDelay());
                }
            } else if (!empty($job = $job_Queue->getNextBuriedJob())) { // Process Pending Buried Jobs
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
            } else {
                sleep(1);
                continue;
            }
        }
    }
}
