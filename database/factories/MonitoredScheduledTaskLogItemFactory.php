<?php

namespace JapSeyz\ScheduleMonitor\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

class MonitoredScheduledTaskLogItemFactory extends Factory
{
    protected $model = MonitoredScheduledTaskLogItem::class;

    public function definition(): array
    {
        return [
            'monitored_scheduled_task_id' => MonitoredScheduledTask::factory(),
            'type' => $this->faker->randomElement([
                MonitoredScheduledTaskLogItem::TYPE_STARTING,
                MonitoredScheduledTaskLogItem::TYPE_FINISHED,
            ]),
            'meta' => [],
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function(MonitoredScheduledTaskLogItem $logItem) {
            $scheduledTask = $logItem->monitoredScheduledTask;
            $scheduledTask->save();

            return $logItem;
        });
    }
}
