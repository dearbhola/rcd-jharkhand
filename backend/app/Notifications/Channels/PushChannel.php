<?php

namespace App\Notifications\Channels;

class PushChannel extends GatewayChannel
{
    protected function name(): string
    {
        return 'push';
    }
}
