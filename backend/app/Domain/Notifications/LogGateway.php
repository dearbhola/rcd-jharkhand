<?php

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Log;

/** Development driver: writes the message to the log instead of sending it. */
class LogGateway implements MessageGateway
{
    public function __construct(private readonly string $channel) {}

    public function send(string $to, string $message, array $meta = []): void
    {
        Log::info("[{$this->channel}] to {$to}: {$message}", $meta);
    }
}
