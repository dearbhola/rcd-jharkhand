<?php

namespace App\Notifications\Channels;

class SmsChannel extends GatewayChannel
{
    protected function name(): string
    {
        return 'sms';
    }
}
