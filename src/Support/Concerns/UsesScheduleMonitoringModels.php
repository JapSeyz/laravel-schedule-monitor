<?php

namespace JapSeyz\ScheduleMonitor\Support\Concerns;

use function app;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

trait UsesScheduleMonitoringModels
{
    public function getMonitoredScheduleTaskModel(): MonitoredScheduledTask
    {
        return app(MonitoredScheduledTask::class);
    }

    public function getMonitoredScheduleTaskLogItemModel(): MonitoredScheduledTaskLogItem
    {
        return app(MonitoredScheduledTaskLogItem::class);
    }
}
