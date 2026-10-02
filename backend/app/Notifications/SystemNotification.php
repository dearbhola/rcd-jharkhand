<?php

namespace App\Notifications;

use App\Domain\Notifications\ChannelRouter;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification not tied to one report (e.g. delegation started/ended).
 */
class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $message,
        public readonly ?string $url = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return array_map(fn ($c) => match ($c) {
            'sms' => SmsChannel::class, 'whatsapp' => WhatsAppChannel::class, 'push' => PushChannel::class, default => $c,
        }, app(ChannelRouter::class)->channelsFor($notifiable, $this->event));
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'message' => $this->message, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('RCD notification')->line($this->message)->when($this->url, fn ($m) => $m->action('Open', url($this->url)));
    }

    public function toText(object $notifiable): string
    {
        return 'RCD: '.$this->message;
    }
}
