<?php

namespace JapSeyz\ScheduleMonitor\Commands;

use Illuminate\Console\Command;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesScheduleMonitoringModels;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\ScheduledTasks;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks\Task;
use function Termwind\render;

class SyncCommand extends Command
{
    use UsesScheduleMonitoringModels;

    public $signature = 'schedule-monitor:sync {--keep-old}';

    public $description = 'Sync the schedule of the app with the schedule monitor';

    public function handle()
    {
        render(view('schedule-monitor::alert', [
            'message' => 'Start syncing schedule...',
            'class' => 'text-green',
        ]));

        $this
            ->storeScheduledTasksInDatabase();

        $monitoredScheduledTasksCount = $this->getMonitoredScheduleTaskModel()->count();

        render(view('schedule-monitor::sync', [
            'monitoredScheduledTasksCount' => $monitoredScheduledTasksCount,
        ]));
    }

    protected function storeScheduledTasksInDatabase(): self
    {
        render(view('schedule-monitor::alert', [
            'message' => 'Start syncing schedule with database...',
        ]));

        $monitoredScheduledTasks = ScheduledTasks::createForSchedule()
            ->uniqueTasks()
            ->map(function (Task $task) {
                return $this->getMonitoredScheduleTaskModel()->updateOrCreate(
                    ['name' => $task->name()],
                    [
                        'type' => $task->type(),
                        'cron_expression' => $task->cronExpression(),
                        'timezone' => $task->timezone(),
                        'grace_time_in_minutes' => $task->graceTimeInMinutes(),
                        'next_start_time' => $task->nextRunAt($task->lastRunFinishedAt()),
                    ]
                );
            });

        if (! $this->option('keep-old')) {
            $this->getMonitoredScheduleTaskModel()->query()
                ->whereNotIn('id', $monitoredScheduledTasks->pluck('id'))
                ->delete();
        }

        return $this;
    }
}
