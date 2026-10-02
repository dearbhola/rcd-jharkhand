<?php

namespace App\Domain\Notifications;

/**
 * Outbound text channel (SMS, WhatsApp, push). One implementation per provider;
 * providers are not chosen yet, so log drivers are bound by default.
 */
interface MessageGateway
{
    /** @throws \Throwable on delivery failure */
    public function send(string $to, string $message, array $meta = []): void;
}
