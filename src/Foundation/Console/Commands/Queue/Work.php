<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Queue;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\BurriedJobRunner;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Console\JobRunner;

class work extends Command
{
    public string $signature = 'queue:work';

    public function handle(array $arguments = []): int
    {
        try {
            call_user_func(new JobRunner);
            call_user_func(new BurriedJobRunner);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return $e->getCode();
        }
        return 0;
    }
}
