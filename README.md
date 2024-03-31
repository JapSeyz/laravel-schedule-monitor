# Monitor scheduled tasks in a Laravel app

Monitor your scheduled tasks in a Laravel app, and notify you in case of failures.

Forked from japseyz/laravel-schedule-monitor

## Installation

You can install the package via composer:

```bash
composer require japseyz/laravel-schedule-monitor
```

#### Preparing the database

You must publish and run migrations:

```bash
php artisan vendor:publish --provider="JapSeyz\ScheduleMonitor\ScheduleMonitorServiceProvider" --tag="schedule-monitor-migrations"
php artisan migrate
```

#### Publishing the config file

You can publish the config file with:
```bash
php artisan vendor:publish --provider="JapSeyz\ScheduleMonitor\ScheduleMonitorServiceProvider" --tag="schedule-monitor-config"
```

This is the contents of the published config file:

```php
return [
    /*
     * The schedule monitor will log each start, finish and failure of all scheduled jobs.
     * After a while the `monitored_scheduled_task_log_items` might become big.
     * Here you can specify the amount of days log items should be kept.
     *
     * Use Laravel's pruning command to delete old `MonitoredScheduledTaskLogItem` models.
     * More info: https://laravel.com/docs/9.x/eloquent#mass-assignment
     */
    'delete_log_items_older_than_days' => 30,

    /*
     * The date format used for all dates displayed on the output of commands
     * provided by this package.
     */
    'date_format' => 'Y-m-d H:i:s',
    
    'notify' => [
        'email' => null,
        'queue' => null,

        'failed' => true,
        'overdue' => true,
    ],

    'models' => [
        /*
         * The model you want to use as a MonitoredScheduledTask model needs to extend the
         * `JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask` Model.
         */
        'monitored_scheduled_task' => JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask::class,

        /*
         * The model you want to use as a MonitoredScheduledTaskLogItem model needs to extend the
         * `JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem` Model.
         */
        'monitored_scheduled_log_item' => JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem::class,
    ],
];
```

#### Cleaning the database

The schedule monitor will log each start, finish and failure of all scheduled jobs.  After a while the `monitored_scheduled_task_log_items` might become big.

Use [Laravel's model pruning feature](https://laravel.com/docs/9.x/eloquent#pruning-models) , you can delete old `MonitoredScheduledTaskLogItem` models. Models older than the amount of days configured in the `delete_log_items_older_than_days` in the `schedule-monitor` config file, will be deleted.

```php
// app/Console/Kernel.php

use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('model:prune', ['--model' => MonitoredScheduledTaskLogItem::class])->daily();
    }
}
```

#### Syncing the schedule

Every time you deploy your application, you should execute the `schedule-monitor:sync` command

```bash
php artisan schedule-monitor:sync
```

In a non-production environment you should manually run `schedule-monitor:sync`. You can verify if everything synced correctly using `schedule-monitor:list`.

**Note:** Running the sync command will remove any other cron monitors that you've defined other than the application schedule.

## Usage

To monitor your schedule you should first run `schedule-monitor:sync`. This command will take a look at your schedule and create an entry for each task in the `monitored_scheduled_tasks` table.

To view all monitored scheduled tasks, you can run `schedule-monitor:list`. This command will list all monitored scheduled tasks. It will show you when a scheduled task has last started, finished, or failed.

The package will write an entry to the `monitored_scheduled_task_log_items` table in the db each time a schedule tasks starts, end, or fails. Take a look at the contest of that table if you want to know when and how scheduled tasks did execute. The log items also hold other interesting metrics like memory usage, execution time, and more.

## Overdue tasks

If you want to be notified about overdue tasks, you should add the following to the `app/Console/Kernel.php` file. 

```php
// app/Console/Kernel.php

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('schedule-monitor:notify-overdue')->everyFiveMinutes();
    }
}
```

### Naming tasks

Schedule monitor will try to automatically determine a name for a scheduled task. For commands this is the command name, for anonymous jobs the class name of the first argument will be used. For some tasks, like scheduled closures, a name cannot be determined automatically.

To manually set a name of the scheduled task,  you can tack on `monitorName()`.

Here's an example.

```php
// in app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
   $schedule->command('your-command')->daily()->monitorName('a-custom-name');
   $schedule->call(fn () => 1 + 1)->hourly()->monitorName('addition-closure');
}
```

When you change the name of task, the schedule monitor will remove all log items of the monitor with the old name, and create a new monitor using the new name of the task.

### Setting a grace time

When the package detects that the last run of a scheduled task did not run in time, the `schedule-monitor` list will display that task using a red background color. In this screenshot the task named `your-command` ran too late.

The package will determine that a task ran too late if it was not finished at the time it was supposed to run + the grace time. You can think of the grace time as the number of minutes that a task under normal circumstances needs to finish. By default, the package grants a grace time of 5 minutes to each task.

You can customize the grace time by using the `graceTimeInMinutes` method on a task. In this example a grace time of 10 minutes is used for the `your-command` task.

```php
// in app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
   $schedule->command('your-command')->daily()->graceTimeInMinutes(10);
}
```

### Ignoring scheduled tasks

You can avoid a scheduled task being monitored by tacking on `doNotMonitor` when scheduling the task.

```php
// in app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
   $schedule->command('your-command')->daily()->doNotMonitor();
}
```

### Storing output in the database

You can store the output by tacking on `storeOutputInDb` when scheduling the task.

```php
// in app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
   $schedule->command('your-command')->daily()->storeOutputInDb();
}
```

The output will be stored in the `monitored_scheduled_task_log_items` table, in the `output` key of the `meta` column.

### Getting notified when a scheduled task doesn't finish in time

This package will automatically send you an email when a scheduled task doesn't finish in time.
To enable this feature, you should set the `notify_email` key in the config file.

Here's an example where it will send a notification if the task didn't finish by 00:10.

```php
// in app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
   $schedule->command('your-command')->daily()->graceTimeInMinutes(10);
}
```

## Unsupported methods

Currently, this package does not work for tasks that use these methods:

- `between`
- `unlessBetween`
- `when`
- `skip`

## Testing

``` bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
