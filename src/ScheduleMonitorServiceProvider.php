<?php

namespace JapSeyz\ScheduleMonitor;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Event as SchedulerEvent;
use Illuminate\Support\Facades\Event;
use JapSeyz\ScheduleMonitor\Commands\ListCommand;
use JapSeyz\ScheduleMonitor\Commands\NotifyOverdueCommand;
use JapSeyz\ScheduleMonitor\Commands\SyncCommand;
use JapSeyz\ScheduleMonitor\EventHandlers\BackgroundCommandListener;
use JapSeyz\ScheduleMonitor\EventHandlers\ScheduledTaskEventSubscriber;
use JapSeyz\ScheduleMonitor\Exceptions\InvalidClassException;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScheduleMonitorServiceProvider extends PackageServiceProvider
{
    private string $monitorName;

    private int $graceTimeInMinutes;

    private bool $doNotMonitor;

    private bool $storeOutputInDb;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-schedule-monitor')
            ->hasViews()
            ->hasConfigFile()
            ->hasMigrations('create_schedule_monitor_tables')
            ->hasCommands([
                ListCommand::class,
                SyncCommand::class,
                NotifyOverdueCommand::class,
            ]);
    }

    public function packageBooted()
    {
        $this
            ->registerEventHandlers()
            ->registerSchedulerEventMacros()
            ->registerModelBindings();
    }

    protected function registerModelBindings()
    {
        $config = config('schedule-monitor.models');

        $this->app->bind(MonitoredScheduledTask::class, $config['monitored_scheduled_task']);
        $this->app->bind(MonitoredScheduledTaskLogItem::class, $config['monitored_scheduled_log_item']);

        $this->protectAgainstInvalidClassDefinition(MonitoredScheduledTask::class, app($config['monitored_scheduled_task']));
        $this->protectAgainstInvalidClassDefinition(MonitoredScheduledTaskLogItem::class, app($config['monitored_scheduled_log_item']));

        return $this;
    }

    protected function registerEventHandlers(): self
    {
        Event::subscribe(ScheduledTaskEventSubscriber::class);
        Event::listen(CommandStarting::class, BackgroundCommandListener::class);

        return $this;
    }

    protected function registerSchedulerEventMacros(): self
    {
        SchedulerEvent::macro('monitorName', function (string $monitorName) {
            $this->monitorName = $monitorName;

            return $this;
        });

        SchedulerEvent::macro('graceTimeInMinutes', function (int $graceTimeInMinutes) {
            $this->graceTimeInMinutes = $graceTimeInMinutes;

            return $this;
        });

        SchedulerEvent::macro('doNotMonitor', function (bool $bool = true) {
            $this->doNotMonitor = $bool;

            return $this;
        });

        SchedulerEvent::macro('storeOutputInDb', function () {
            $this->storeOutputInDb = true;
            /** @psalm-suppress UndefinedMethod */
            $this->ensureOutputIsBeingCaptured();

            return $this;
        });

        return $this;
    }

    protected function protectAgainstInvalidClassDefinition($packageClass, $providedModel): void
    {
        if (! ($providedModel instanceof $packageClass)) {
            $providedClass = get_class($providedModel);

            throw new InvalidClassException("The provided class name {$providedClass} does not extend the required package class {$packageClass}.");
        }
    }
}
