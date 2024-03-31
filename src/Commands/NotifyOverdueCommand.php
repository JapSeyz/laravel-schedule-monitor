<?php

namespace JapSeyz\ScheduleMonitor\Commands;

use Illuminate\Console\Command;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesScheduleMonitoringModels;

class NotifyOverdueCommand extends Command
{
    use UsesScheduleMonitoringModels;

    public $signature = 'schedule-monitor:notify-overdue';

    public $description = 'Notifies the user of overdue commands';

    public function handle()
    {
        $this->getMonitoredScheduleTaskModel()
            ->overdue()
            ->get()
            ->each(fn (MonitoredScheduledTask $task) => $task->notifyOverdue());
    }
}
