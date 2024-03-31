<x-mail::message>
Your scheduled job **{{ $task->name }}** has failed to run and is overdue.

- The command was expected to run at: **{{ $task->next_start_time }}.**
- The last run finished at: {{ $task->last_finished_at }}.
- The last run failed at: {{ $task->last_failed_at }}.

Server: {{ config('app.name') }}

</x-mail::message>
