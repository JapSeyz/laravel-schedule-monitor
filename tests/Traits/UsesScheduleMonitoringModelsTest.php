<?php

use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesScheduleMonitoringModels;

it('can resolve schedule monitoring models', function () {
    $model = new class() {
        use UsesScheduleMonitoringModels;
    };

    $monitorScheduleTask = $model->getMonitoredScheduleTaskModel();
    $monitorScheduleTaskLogItem = $model->getMonitoredScheduleTaskLogItemModel();

    expect($monitorScheduleTask)->toBeInstanceOf(MonitoredScheduledTask::class);
    expect($monitorScheduleTaskLogItem)->toBeInstanceOf(MonitoredScheduledTaskLogItem::class);
});
