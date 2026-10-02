<?php

namespace App\Notifications\Channels;

use App\Domain\Notifications\ChannelRouter;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Base for SMS / WhatsApp / push channels. Every attempt is recorded in notification_deliveries.
 */
abstract class GatewayChannel
{
    abstract protected function name(): string;

    public function __construct(private readonly ChannelRouter $router) {}

    public function send(User $notifiable, Notification $notification): void
    {
        $to = $this->router->destination($notifiable, $this->name());
        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'user_id' => $notifiable->id,
            'channel' => $this->name(),
            'event' => $notification->event ?? class_basename($notification),
            'status' => 'pending',
            'attempts' => 1,
        ]);

        try {
            app("rcd.gateway.{$this->name()}")->send((string) $to, $notification->toText($notifiable), ['notification_id' => $notification->id]);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $delivery->update(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);
        }
    }
}
