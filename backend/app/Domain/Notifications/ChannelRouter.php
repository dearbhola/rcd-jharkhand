<?php

namespace App\Domain\Notifications;

use App\Models\User;
use App\Support\Settings;

/**
 * Decides the channels for a notification: always in-app (database); external channels
 * only when enabled in settings, listed for the event, and the user has a destination.
 */
class ChannelRouter
{
    public const EXTERNAL = ['mail', 'sms', 'whatsapp', 'push'];

    public function __construct(private readonly Settings $settings) {}

    /** @return list<string> */
    public function channelsFor(User $user, string $event): array
    {
        $channels = ['database'];
        if (! in_array($event, $this->settings->get('notifications.external_events', []), true)) {
            return $channels;
        }

        foreach (self::EXTERNAL as $channel) {
            if ($this->settings->bool("notifications.{$channel}_enabled") && $this->destination($user, $channel)) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    public function destination(User $user, string $channel): ?string
    {
        return match ($channel) {
            'mail' => $user->email,
            'sms', 'whatsapp' => $user->mobile,
            'push' => $user->devices()->whereNull('revoked_at')->whereNotNull('push_token')->latest('last_seen_at')->value('push_token'),
            default => null,
        };
    }
}
