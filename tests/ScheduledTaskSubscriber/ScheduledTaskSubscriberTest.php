<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use JapSeyz\ScheduleMonitor\Commands\SyncCommand;
use JapSeyz\ScheduleMonitor\Mail\NotifyFailed;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\FailingCommand;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\TestKernel;

beforeEach(function () {
    Mail::fake();

    config(['schedule-monitor.notify.email' => 'test@example.com']);
    config(['schedule-monitor.notify.failed' => true]);
    config()->set('schedule-monitor.notify.queue', 'custom-queue');

    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->call(fn () => 1 + 1)->everyMinute()->monitorName('dummy-task');
    });

    File::copy(__DIR__.'/../stubs/artisan', base_path('artisan'));
});

afterEach(function () {
    File::delete(base_path('artisan'));
});

it('will fire a job and log failures of scheduled tasks', function () {
    TestKernel::replaceScheduledTasks(function (Schedule $schedule) {
        $schedule
            ->call(function () {
                throw new Exception("exception");
            })
            ->everyMinute()
            ->monitorName('failing-task');
    });

    $this->artisan(SyncCommand::class)->assertExitCode(0);
    $this->artisan('schedule:run')->assertExitCode(0);

    Mail::assertSent(function (NotifyFailed $mail) {
        return $mail->queue === 'custom-queue';
    });

    $logTypes = MonitoredScheduledTask::findByName('failing-task')
        ->logItems
        ->pluck('type')
        ->toArray();

    $this->assertEquals([
        MonitoredScheduledTaskLogItem::TYPE_FAILED,
        MonitoredScheduledTaskLogItem::TYPE_STARTING,
    ], $logTypes);
});

it('will mark a task as failed when it throws an exception', function () {
    File::delete(base_path('artisan'));

    TestKernel::replaceScheduledTasks(function (Schedule $schedule) {
        $schedule->command(FailingCommand::class)->everyMinute();
    });

    $this->artisan(SyncCommand::class)->assertExitCode(0);
    $this->artisan('schedule:run')->assertExitCode(0);

    $logTypes = MonitoredScheduledTask::findByName('failing-command')
        ->logItems
        ->pluck('type')
        ->values()
        ->toArray();

    $this->assertEquals([
        MonitoredScheduledTaskLogItem::TYPE_FAILED,
        MonitoredScheduledTaskLogItem::TYPE_STARTING,
    ], $logTypes);
});

it('will not fire a job when a scheduled task finished that is not monitored', function () {
    $this->artisan('schedule:run')->assertExitCode(0);

    Mail::assertNothingSent();
});

it('stores the command output to db', function () {
    TestKernel::replaceScheduledTasks(function (Schedule $schedule) {
        $schedule
            ->command('help')
            ->everyMinute()
            ->storeOutputInDb()
            ->monitorName('dummy-task');
    });

    $this->artisan(SyncCommand::class)->assertExitCode(0);
    $this->artisan('schedule:run')->assertExitCode(0);

    $task = MonitoredScheduledTask::findByName('dummy-task');
    $logItem = $task->logItems()->where('type', MonitoredScheduledTaskLogItem::TYPE_FINISHED)->first();

    expect($logItem->meta['output'] ?? '')->toContain('help for a command');
});

it('does not store the command output to db', function () {
    TestKernel::replaceScheduledTasks(function (Schedule $schedule) {
        $schedule
            ->command('help')
            ->everyMinute()
            ->monitorName('dummy-task');
    });

    $this->artisan(SyncCommand::class)->assertExitCode(0);
    $this->artisan('schedule:run')->assertExitCode(0);

    $task = MonitoredScheduledTask::findByName('dummy-task');
    $logItem = $task->logItems()->where('type', MonitoredScheduledTaskLogItem::TYPE_FINISHED)->first();

    expect($logItem->meta['output'])->toBeNull();
});
