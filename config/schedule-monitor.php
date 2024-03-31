<?php

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
