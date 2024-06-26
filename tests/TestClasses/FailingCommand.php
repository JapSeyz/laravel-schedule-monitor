<?php

namespace JapSeyz\ScheduleMonitor\Tests\TestClasses;

use Exception;
use Illuminate\Console\Command;

class FailingCommand extends Command
{
    public static bool $executed = false;

    public $signature = 'failing-command';

    public function handle()
    {
        $this->info('Starting failed command...');

        throw new Exception('failing');
    }
}
