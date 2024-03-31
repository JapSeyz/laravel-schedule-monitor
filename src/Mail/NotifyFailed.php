<?php

namespace JapSeyz\ScheduleMonitor\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

class NotifyFailed extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public MonitoredScheduledTaskLogItem $logItem)
    {
        if ($queue = config('schedule-monitor.notify.queue')) {
            $this->onQueue($queue);
        }
    }

    public function envelope(): Envelope
    {
        $to = preg_split('/\s*[,;]\s*/', config('schedule-monitor.notify.email'));

        return new Envelope(
            to: $to,
            subject: $this->logItem->monitoredScheduledTask->name . ' did not run as expected on ' . config('app.name')
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'schedule-monitor::mails.notify-failed',
        );
    }

    public function send($mailer)
    {
        parent::send($mailer);

        $this->logItem->monitoredScheduledTask->touch('last_notified_at');
    }
}
