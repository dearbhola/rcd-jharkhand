<?php

namespace App\Notifications;

use App\Domain\Notifications\ChannelRouter;
use App\Models\Report;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification about a report/task. Always stored in-app; external channels per ChannelRouter.
 */
class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly int $reportId;

    public readonly string $reportNo;

    public readonly string $status;

    /** Plain values only: queued jobs must not depend on re-loading the report (test data may be hidden in workers). */
    public function __construct(
        public readonly string $event,
        Report $report,
        public readonly string $message,
        ?string $status = null,
    ) {
        $this->reportId = $report->id;
        $this->reportNo = $report->report_no;
        $this->status = $status ?? $report->status;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return array_map(fn ($c) => match ($c) {
            'sms' => SmsChannel::class,
            'whatsapp' => WhatsAppChannel::class,
            'push' => PushChannel::class,
            default => $c,
        }, app(ChannelRouter::class)->channelsFor($notifiable, $this->event));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'report_id' => $this->reportId,
            'report_no' => $this->reportNo,
            'status' => $this->status,
            'message' => $this->message,
            'url' => route('reports.show', $this->reportId, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("RCD: {$this->reportNo}")
            ->line($this->message)
            ->action('Open report', route('reports.show', $this->reportId));
    }

    public function toText(object $notifiable): string
    {
        return 'RCD: '.$this->message;
    }
}
