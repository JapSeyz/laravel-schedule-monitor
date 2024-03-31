<?php

namespace JapSeyz\ScheduleMonitor\Tests\Commands;

use Illuminate\Console\Scheduling\Schedule;
use JapSeyz\ScheduleMonitor\Commands\ListCommand;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\TestJob;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\TestKernel;
use function Pest\Laravel\artisan;

it('can list scheduled tasks', function () {
    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('dummy')->everyMinute();
        $schedule->exec('execute')->everyFifteenMinutes();
        $schedule->call(fn () => 1 + 1)->hourly();
        $schedule->job(new TestJob())->daily();
        $schedule->job(new TestJob())->daily();
    });

    artisan(ListCommand::class)->assertSuccessful();
});
