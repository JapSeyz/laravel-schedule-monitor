<?php

namespace JapSeyz\ScheduleMonitor\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use JapSeyz\ScheduleMonitor\Models\MonitoredScheduledTask;

class NotifyOverdue extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public MonitoredScheduledTask $task)
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
            subject: $this->task->name . ' is overdue on ' . config('app.name')
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'schedule-monitor::mails.notify-overdue',
        );
    }

    public function send($mailer)
    {
        parent::send($mailer);

        $this->task->touch('last_notified_at');
    }
}
