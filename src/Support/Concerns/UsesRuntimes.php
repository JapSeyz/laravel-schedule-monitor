<?php

namespace JapSeyz\ScheduleMonitor\Support\Concerns;

use Carbon\CarbonInterface;
use function config;
use Cron\CronExpression;
use Illuminate\Support\Facades\Date;
use function now;

trait UsesRuntimes
{
    public function nextRunAt(CarbonInterface $now = null): CarbonInterface
    {
        $dateTime = (new CronExpression($this->cronExpression()))->getNextRunDate(
            $now ?? now(),
            0,
            false,
            $this->timezone()
        );

        $date = Date::instance($dateTime);

        $date->setTimezone(config('app.timezone'));

        return $date;
    }
}
