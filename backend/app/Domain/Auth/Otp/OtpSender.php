<?php

namespace App\Domain\Auth\Otp;

/**
 * SMS gateway abstraction. The provider is not yet decided (D5).
 */
interface OtpSender
{
    public function send(string $mobile, string $code, string $purpose, int $ttlMinutes): void;
}
