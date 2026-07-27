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
        $dbtype = config('database.connections.mysql.driver');
        $dbhost = config('database.connections.mysql.host');
        $dbname = config('database.connections.mysql.database');
        $dbuser = config('database.connections.mysql.username');
        $dbpass = config('database.connections.mysql.password');
        $dbport = config('database.connections.mysql.port');
        $dbcharset = config('database.connections.mysql.charset');

        $job_Queue = new Job_Queue(Job_Queue::QUEUE_TYPE_MYSQL, [
            $dbtype => [
                'table_name' => 'jobs',     //the table that jobs will be stored in
                'use_compression' => true
            ]
        ]);

        $pdo = new PDO("$dbtype:dbname=$dbname;host=$dbhost", $dbuser, $dbpass);
        $job_Queue->addQueueConnection($pdo);
        $job_Queue->watchPipeline('default');

        while (true) {
            // Process Pending Jobs
            if(!empty($job = $job_Queue->getNextJobAndReserve())) {
                $payload = $job['payload'];
    
                try {
                    $job_obj = \Eyika\Atom\Framework\Support\SignedPayload::verify($payload);
    
                    if ($job_obj instanceof QueueInterface) {
                        $job_obj->setJob($job);
                        $job_obj->setQueue($job_Queue);
                        $resp = $job_obj->handle();
                    } else {
                        info('job object is not an instance of Queue Interface');
                    }
                } catch(Exception $e) {
                    $job_Queue->buryJob($job, $job_obj->getDelay());
                    throw $e;
                }
            } else if (!empty($job = $job_Queue->getNextBuriedJob())) { // Process Pending Buried Jobs
                $payload = $job['payload'];

                try {
                    $job_obj = \Eyika\Atom\Framework\Support\SignedPayload::verify($payload);
    
                    if ($job_obj instanceof QueueInterface) {
                        $job_obj->setJob($job);
                        $job_obj->setQueue($job_Queue);
                        $resp = $job_obj->handle();
                    } else {
                        info('buried job object is not an instance of Queue Interface');
                    }
                } catch (Exception $e) {
                    $job_Queue->buryJob($job, $job_obj->getDelay());
                    throw $e;
                }
            } else {
                break;
            }
        }
    }
}
