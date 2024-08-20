<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Schedule;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\BurriedJobRunner;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Console\JobRunner;

class Run extends Command
{
    public string $signature = 'schedule:run';

    public function handle(array $arguments = []): bool
    {
        try {
            call_user_func(new JobRunner);
            call_user_func(new BurriedJobRunner);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
    }
}
