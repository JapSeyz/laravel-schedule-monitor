<?php

namespace JapSeyz\ScheduleMonitor\Support\ScheduledTasks;

use Illuminate\Console\Scheduling\Event;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks\ClosureTask;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks\CommandTask;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks\JobTask;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks\ShellTask;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks\Task;

class ScheduledTaskFactory
{
    public static function createForEvent(Event $event): Task
    {
        $taskClass = collect([
            ClosureTask::class,
            JobTask::class,
            CommandTask::class,
            ShellTask::class,
        ])
            ->first(fn (string $taskClass) => $taskClass::canHandleEvent($event));

        return new $taskClass($event);
    }
}
