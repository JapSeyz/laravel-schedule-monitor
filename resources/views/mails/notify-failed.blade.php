<x-mail::message>
Your scheduled job **{{ $logItem->monitoredScheduledTask->name }}** did not run as expected.

- The last run finished at: {{ $logItem->monitoredScheduledTask->last_finished_at }}.
- The last run failed at: {{ $logItem->monitoredScheduledTask->last_failed_at }}.

Server: {{ config('app.name') }}

</x-mail::message>
