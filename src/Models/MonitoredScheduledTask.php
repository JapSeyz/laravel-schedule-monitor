<?php

namespace JapSeyz\ScheduleMonitor\Models;

use function config;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use JapSeyz\ScheduleMonitor\Mail\NotifyFailed;
use JapSeyz\ScheduleMonitor\Mail\NotifyOverdue;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesRuntimes;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesScheduleMonitoringModels;
use JapSeyz\ScheduleMonitor\Support\ScheduledTasks\ScheduledTaskFactory;
use function now;

class MonitoredScheduledTask extends Model
{
    use UsesScheduleMonitoringModels;
    use UsesRuntimes;
    use HasFactory;

    public $guarded = [];

    protected $casts = [
        'last_notified_at' => 'datetime',
        'last_started_at' => 'datetime',
        'last_finished_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'next_start_time' => 'datetime',
        'grace_time_in_minutes' => 'integer',
    ];

    public function logItems(): HasMany
    {
        return $this->hasMany($this->getMonitoredScheduleTaskLogItemModel(), 'monitored_scheduled_task_id')->orderByDesc('id');
    }

    public static function findByName(string $name): ?self
    {
        $monitoredScheduledTask = new static();

        return $monitoredScheduledTask
            ->getMonitoredScheduleTaskModel()
            ->where('name', $name)
            ->first();
    }

    public static function findForTask(Event $event): ?self
    {
        $task = ScheduledTaskFactory::createForEvent($event);
        $monitoredScheduledTask = new static();

        if (empty($task->name())) {
            return null;
        }

        return $monitoredScheduledTask
            ->getMonitoredScheduleTaskModel()
            ->findByName($task->name());
    }

    public function markAsStarting(ScheduledTaskStarting $event): self
    {
        $logItem = $this->createLogItem($this->getMonitoredScheduleTaskLogItemModel()::TYPE_STARTING);

        $logItem->updateMeta([
            'memory' => memory_get_usage(true),
        ]);


        $this->update([
            'last_started_at' => now(),
        ]);

        return $this;
    }

    public function markAsFinished(ScheduledTaskFinished $event): self
    {
        if ($this->eventConcernsBackgroundTaskThatCompletedInForeground($event)) {
            return $this;
        }

        if ($event->task->exitCode !== 0 && ! is_null($event->task->exitCode)) {
            return $this->markAsFailed($event);
        }

        $logItem = $this->createLogItem($this->getMonitoredScheduleTaskLogItemModel()::TYPE_FINISHED);

        $logItem->updateMeta([
            'runtime' => $event->task->runInBackground ? 0 : $event->runtime,
            'exit_code' => $event->task->exitCode,
            'memory' => $event->task->runInBackground ? 0 : memory_get_usage(true),
            'output' => $this->getEventTaskOutput($event),
        ]);

        $this->update([
            'last_finished_at' => now(),
            'next_start_time' => $this->nextRunAt(),
        ]);

        return $this;
    }

    public function eventConcernsBackgroundTaskThatCompletedInForeground(ScheduledTaskFinished $event): bool
    {
        if (! $event->task->runInBackground) {
            return false;
        }

        return $event->task->exitCode === null;
    }

    /**
     * @param ScheduledTaskFailed|ScheduledTaskFinished $event
     *
     * @return $this
     */
    public function markAsFailed($event): self
    {
        $logItem = $this->createLogItem($this->getMonitoredScheduleTaskLogItemModel()::TYPE_FAILED);

        if ($event instanceof ScheduledTaskFailed) {
            $logItem->updateMeta([
                'failure_message' => Str::limit(optional($event->exception)->getMessage(), 255),
            ]);
        }

        if ($event instanceof ScheduledTaskFinished) {
            $logItem->updateMeta([
                'runtime' => $event->runtime,
                'exit_code' => $event->task->exitCode,
                'memory' => memory_get_usage(true),
                'output' => $this->getEventTaskOutput($event),
            ]);
        }

        $this->update(['last_failed_at' => now()]);

        $this->notifyFailed($logItem);

        return $this;
    }

    public function notifyOverdue(): self
    {
        if (config('schedule-monitor.notify.overdue') && config('schedule-monitor.notify.email')) {
            Mail::send(new NotifyOverdue($this));
        }

        return $this;
    }

    public function notifyFailed(MonitoredScheduledTaskLogItem $logItem): self
    {
        if (config('schedule-monitor.notify.failed') && config('schedule-monitor.notify.email')) {
            Mail::send(new NotifyFailed($logItem));
        }

        return $this;
    }

    public function cronExpression(): string
    {
        return $this->cron_expression;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function createLogItem(string $type): MonitoredScheduledTaskLogItem
    {
        return $this->logItems()->create([
            'type' => $type,
        ]);
    }

    /**
     * @param ScheduledTaskFailed|ScheduledTaskFinished $event
     */
    public function getEventTaskOutput($event): ?string
    {
        if (! ($event->task->storeOutputInDb ?? false)) {
            return null;
        }

        if (is_null($event->task->output)) {
            return null;
        }

        if ($event->task->output === $event->task->getDefaultOutput()) {
            return null;
        }

        if (! is_file($event->task->output)) {
            return null;
        }

        $output = file_get_contents($event->task->output);

        return $output ?: null;
    }

    public function scopeOverdue($builder): void
    {
        $builder->whereRaw('next_start_time + INTERVAL grace_time_in_minutes MINUTE < NOW()')
            ->where(function ($query) {
                $query->whereNull('last_notified_at')
                    ->orWhere(DB::raw('last_notified_at + INTERVAL grace_time_in_minutes MINUTE + INTERVAL 24 HOUR < NOW()'));
            });
    }
}
