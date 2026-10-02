<?php

namespace App\Notifications\Channels;

class WhatsAppChannel extends GatewayChannel
{
    protected function name(): string
    {
        return 'whatsapp';
    }
}
