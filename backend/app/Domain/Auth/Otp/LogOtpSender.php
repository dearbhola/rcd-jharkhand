<?php

namespace App\Domain\Auth\Otp;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Development driver: writes the OTP to the application log. Refuses to run in production.
 */
class LogOtpSender implements OtpSender
{
    public function send(string $mobile, string $code, string $purpose, int $ttlMinutes): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Log OTP driver is not allowed in production. Configure an SMS gateway.');
        }

        Log::info("[OTP] {$purpose} for {$mobile}: {$code} (valid {$ttlMinutes} min)");
    }
}
