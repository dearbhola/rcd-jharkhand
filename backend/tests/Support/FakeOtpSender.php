<?php

namespace Tests\Support;

use App\Domain\Auth\Otp\OtpSender;

class FakeOtpSender implements OtpSender
{
    /** @var list<array{mobile: string, code: string, purpose: string}> */
    public array $sent = [];

    public function send(string $mobile, string $code, string $purpose, int $ttlMinutes): void
    {
        $this->sent[] = compact('mobile', 'code', 'purpose');
    }

    public function lastCodeFor(string $mobile): ?string
    {
        $match = array_values(array_filter($this->sent, fn ($s) => $s['mobile'] === $mobile));

        return $match ? end($match)['code'] : null;
    }
}
