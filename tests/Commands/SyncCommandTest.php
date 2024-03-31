<?php

use Illuminate\Console\Scheduling\Schedule;
use JapSeyz\ScheduleMonitor\Commands\SyncCommand;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\TestJob;
use JapSeyz\ScheduleMonitor\Tests\TestClasses\TestKernel;
use Spatie\TestTime\TestTime;

beforeEach(function () {
    TestTime::freeze('Y-m-d H:i:s', '2020-01-01 00:00:00');
});

it('can sync the schedule with the db', function () {
    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('dummy')->everyMinute();
        $schedule->exec('execute')->everyFifteenMinutes();
        $schedule->call(fn () => 1 + 1)->hourly()->monitorName('my-closure');
        $schedule->job(new TestJob())->daily()->timezone('Asia/Kolkata');
    });

    $this->artisan(SyncCommand::class);

    $monitoredScheduledTasks = MonitoredScheduledTask::get();
    expect($monitoredScheduledTasks)->toHaveCount(4);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => 'dummy',
        'type' => 'command',
        'cron_expression' => '* * * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => 'execute',
        'type' => 'shell',
        'cron_expression' => '*/15 * * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => 'my-closure',
        'type' => 'closure',
        'cron_expression' => '0 * * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => TestJob::class,
        'type' => 'job',
        'cron_expression' => '0 0 * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'Asia/Kolkata',
    ]);
});

it('can use the keep old option to non destructively update the schedule with db', function () {
    MonitoredScheduledTask::create([
        'name' => 'dummy-1',
        'type' => 'command',
        'cron_expression' => '* * * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);

    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('dummy-2')->hourly();
        $schedule->command('dummy-3')->daily();
    });

    $this->artisan(SyncCommand::class, ['--keep-old' => true]);

    $monitoredScheduledTasks = MonitoredScheduledTask::get();
    expect($monitoredScheduledTasks)->toHaveCount(3);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => 'dummy-1',
        'type' => 'command',
        'cron_expression' => '* * * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => 'dummy-2',
        'type' => 'command',
        'cron_expression' => '0 * * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'name' => 'dummy-3',
        'type' => 'command',
        'cron_expression' => '0 0 * * *',
        'grace_time_in_minutes' => 5,
        'last_notified_at' => null,
        'last_started_at' => null,
        'last_finished_at' => null,
        'timezone' => 'UTC',
    ]);
});

it('will not monitor commands without a name', function () {
    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->call(fn () => 'a closure has no name')->hourly();
    });

    $this->artisan(SyncCommand::class);

    $monitoredScheduledTasks = MonitoredScheduledTask::get();
    expect($monitoredScheduledTasks)->toHaveCount(0);
});

it('will remove old tasks from the database', function () {
    MonitoredScheduledTask::factory()->create(['name' => 'old-task']);
    expect(MonitoredScheduledTask::get())->toHaveCount(1);

    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('new')->everyMinute();
    });

    $this->artisan(SyncCommand::class);

    expect(MonitoredScheduledTask::get())->toHaveCount(1);

    expect(MonitoredScheduledTask::first()->name)->toEqual('new');
});

it('can use custom grace time', function () {
    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('dummy')->everyMinute()->graceTimeInMinutes(15);
    });

    $this->artisan(SyncCommand::class);

    $this->assertDatabaseHas('monitored_scheduled_tasks', [
        'grace_time_in_minutes' => 15,
    ]);
});

it('will not monitor tasks that should not be monitored', function () {
    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('dummy')->everyMinute()->doNotMonitor();
    });

    $this->artisan(SyncCommand::class);

    expect(MonitoredScheduledTask::get())->toHaveCount(0);
});

it('will remove tasks from the db that should not be monitored anymore', function () {
    MonitoredScheduledTask::factory()->create(['name' => 'not-monitored']);
    expect(MonitoredScheduledTask::get())->toHaveCount(1);

    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('not-monitored')->everyMinute()->doNotMonitor();
    });
    $this->artisan(SyncCommand::class);

    expect(MonitoredScheduledTask::get())->toHaveCount(0);
});

it('will update tasks that have their schedule updated', function () {
    $monitoredScheduledTask = MonitoredScheduledTask::factory()->create([
        'name' => 'dummy',
        'cron_expression' => '* * * * *',
    ]);

    TestKernel::registerScheduledTasks(function (Schedule $schedule) {
        $schedule->command('dummy')->daily();
    });
    $this->artisan(SyncCommand::class);

    expect(MonitoredScheduledTask::get())->toHaveCount(1);
    expect($monitoredScheduledTask->refresh()->cron_expression)->toEqual('0 0 * * *');
});
