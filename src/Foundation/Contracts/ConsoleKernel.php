<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

use Eyika\Atom\Framwork\Foundation\Console\Contracts\QueueInterface;

interface ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    public function schedule(QueueInterface $schedule): void;

    /**
     * Register the commands for the application.
     */
    public function commands(): void;
}