<?php

namespace JapSeyz\ScheduleMonitor\Support\ScheduledTasks\Tasks;

use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesRuntimes;
use JapSeyz\ScheduleMonitor\Support\Concerns\UsesScheduleMonitoringModels;
use Lorisleiva\CronTranslator\CronParsingException;
use Lorisleiva\CronTranslator\CronTranslator;

abstract class Task
{
    use UsesScheduleMonitoringModels;
    use UsesRuntimes;

    protected Event $event;

    protected string $uniqueId;

    protected ?MonitoredScheduledTask $monitoredScheduledTask = null;

    abstract public static function canHandleEvent(Event $event): bool;

    abstract public function defaultName(): ?string;

    abstract public function type(): string;

    public function __construct(Event $event)
    {
        $this->event = $event;

        $this->uniqueId = (string)Str::uuid();

        if (! empty($this->name())) {
            $this->monitoredScheduledTask = $this->getMonitoredScheduleTaskModel()->findByName($this->name());
        }
    }

    public function uniqueId(): string
    {
        return $this->uniqueId;
    }

    public function name(): ?string
    {
        return $this->event->monitorName ?? $this->defaultName();
    }

    public function shouldMonitor(): bool
    {
        if (! isset($this->event->doNotMonitor)) {
            return true;
        }

        return ! $this->event->doNotMonitor;
    }

    public function isBeingMonitored(): bool
    {
        return ! is_null($this->monitoredScheduledTask);
    }

    public function previousRunAt(): CarbonInterface
    {
        $dateTime = CronExpression::factory($this->cronExpression())->getPreviousRunDate(now());

        return Date::instance($dateTime);
    }

    public function lastRunStartedAt(): ?CarbonInterface
    {
        return optional($this->monitoredScheduledTask)->last_started_at;
    }

    public function lastRunFinishedAt(): ?CarbonInterface
    {
        return optional($this->monitoredScheduledTask)->last_finished_at;
    }

    public function lastRunFailedAt(): ?CarbonInterface
    {
        return optional($this->monitoredScheduledTask)->last_failed_at;
    }

    public function lastRunFinishedTooLate(): bool
    {
        if (! $this->isBeingMonitored()) {
            return false;
        }

        $lastFinishedAt = $this->lastRunFinishedAt()
            ? $this->lastRunFinishedAt()
            : $this->monitoredScheduledTask->created_at->subSecond();

        $expectedNextRunStart = $this->nextRunAt($lastFinishedAt);
        $shouldHaveFinishedAt = $expectedNextRunStart->addMinutes($this->graceTimeInMinutes());

        return $shouldHaveFinishedAt->isPast();
    }

    public function lastRunFailed(): bool
    {
        if (! $this->isBeingMonitored()) {
            return false;
        }

        if (! $lastRunFailedAt = $this->lastRunFailedAt()) {
            return false;
        }

        if (! $lastRunStartedAt = $this->lastRunStartedAt()) {
            return true;
        }

        return $lastRunFailedAt->isAfter($lastRunStartedAt->subSecond());
    }

    public function graceTimeInMinutes()
    {
        return $this->event->graceTimeInMinutes ?? 5;
    }

    public function cronExpression(): string
    {
        return $this->event->getExpression();
    }

    public function timezone(): string
    {
        return (string)$this->event->timezone;
    }

    public function humanReadableCron(): string
    {
        try {
            return CronTranslator::translate($this->cronExpression());
        } catch (CronParsingException $exception) {
            return $this->cronExpression();
        }
    }

    public function runsInBackground():bool
    {
        return $this->event->runInBackground;
    }
}
