<?php

namespace JapSeyz\ScheduleMonitor\EventHandlers;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Events\Dispatcher;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesScheduleMonitoringModels;

class ScheduledTaskEventSubscriber
{
    use UsesScheduleMonitoringModels;

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            ScheduledTaskStarting::class,
            fn (ScheduledTaskStarting $event) => optional($this->getMonitoredScheduleTaskModel()->findForTask($event->task))->markAsStarting($event)
        );

        $events->listen(
            ScheduledTaskFinished::class,
            fn (ScheduledTaskFinished $event) => optional($this->getMonitoredScheduleTaskModel()->findForTask($event->task))->markAsFinished($event)
        );

        $events->listen(
            ScheduledTaskFailed::class,
            fn (ScheduledTaskFailed $event) => optional($this->getMonitoredScheduleTaskModel()->findForTask($event->task))->markAsFailed($event)
        );
    }
}
