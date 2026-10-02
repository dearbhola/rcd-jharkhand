<?php

namespace App\Domain\Auth\Otp;

use App\Models\OtpVerification;
use App\Support\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const PURPOSE_REGISTER = 'register';

    public const PURPOSE_RESET_PASSWORD = 'reset_password';

    public function __construct(
        private readonly OtpSender $sender,
        private readonly Settings $settings,
    ) {}

    public function send(string $mobile, string $purpose, ?string $ip = null): void
    {
        $cooldown = $this->settings->int('otp.resend_cooldown_seconds');
        $recent = OtpVerification::where('mobile', $mobile)->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds($cooldown))->exists();

        if ($recent) {
            throw ValidationException::withMessages(['mobile' => "Please wait {$cooldown} seconds before requesting another OTP."]);
        }

        $length = $this->settings->int('otp.length');
        $ttl = $this->settings->int('otp.ttl_minutes');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        // Any earlier unused code for the same purpose is invalidated.
        OtpVerification::where('mobile', $mobile)->where('purpose', $purpose)->whereNull('verified_at')
            ->update(['expires_at' => now()]);

        OtpVerification::create([
            'mobile' => $mobile,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'ip_address' => $ip,
        ]);

        $this->sender->send($mobile, $code, $purpose, $ttl);
    }

    /**
     * Verify and consume a code. Throws a validation error on any failure.
     */
    public function verify(string $mobile, string $purpose, string $code): void
    {
        $otp = OtpVerification::where('mobile', $mobile)->where('purpose', $purpose)
            ->whereNull('verified_at')->where('expires_at', '>', now())
            ->latest('id')->first();

        if (! $otp) {
            throw ValidationException::withMessages(['otp' => 'The OTP has expired. Please request a new one.']);
        }

        if ($otp->attempts >= $this->settings->int('otp.max_attempts')) {
            throw ValidationException::withMessages(['otp' => 'Too many incorrect attempts. Please request a new OTP.']);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages(['otp' => 'The OTP is incorrect.']);
        }

        $otp->update(['verified_at' => now()]);
    }
}
