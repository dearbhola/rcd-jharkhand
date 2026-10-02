<?php

namespace Tests\Feature\Phase2;

use App\Domain\Auth\Otp\OtpSender;
use App\Models\User;
use App\Support\Settings;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\FakeOtpSender;

class CitizenRegistrationTest extends SeededTestCase
{
    private FakeOtpSender $sms;

    private const MOBILE = '9876500001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeOtpSender;
        $this->app->instance(OtpSender::class, $this->sms);
        app(Settings::class)->set('auth.sms_otp_enabled', true);
    }

    private function startRegistration(): void
    {
        $this->post('/register', ['name' => 'Asha Kumari', 'mobile' => self::MOBILE])->assertRedirect('/register/verify');
    }

    #[Test]
    public function a_citizen_registers_with_mobile_and_otp(): void
    {
        $this->startRegistration();

        $this->post('/register/verify', [
            'otp' => $this->sms->lastCodeFor(self::MOBILE),
            'password' => 'Citizen#2026x',
            'password_confirmation' => 'Citizen#2026x',
        ])->assertRedirect('/dashboard');

        $user = User::where('mobile', self::MOBILE)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['CITIZEN'], $user->roleCodes()->all());
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertFalse($user->is_test);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.registered', 'auditable_id' => $user->id]);
    }

    #[Test]
    public function a_wrong_otp_is_rejected_and_attempts_are_limited(): void
    {
        $this->startRegistration();
        $max = app(Settings::class)->int('otp.max_attempts');
        $payload = ['otp' => '000000', 'password' => 'Citizen#2026x', 'password_confirmation' => 'Citizen#2026x'];
        if ($this->sms->lastCodeFor(self::MOBILE) === '000000') {
            $payload['otp'] = '111111';
        }

        for ($i = 0; $i < $max; $i++) {
            $this->post('/register/verify', $payload)->assertSessionHasErrors(['otp' => 'The OTP is incorrect.']);
        }

        // Even the right code is refused once attempts are exhausted.
        $payload['otp'] = $this->sms->lastCodeFor(self::MOBILE);
        $this->post('/register/verify', $payload)->assertSessionHasErrors('otp');
        $this->assertDatabaseMissing('users', ['mobile' => self::MOBILE]);
    }

    #[Test]
    public function an_expired_otp_is_rejected(): void
    {
        $this->startRegistration();
        $this->travel(app(Settings::class)->int('otp.ttl_minutes') + 1)->minutes();

        $this->post('/register/verify', [
            'otp' => $this->sms->lastCodeFor(self::MOBILE), 'password' => 'Citizen#2026x', 'password_confirmation' => 'Citizen#2026x',
        ])->assertSessionHasErrors(['otp' => 'The OTP has expired. Please request a new one.']);
    }

    #[Test]
    public function an_already_registered_mobile_is_refused(): void
    {
        $this->post('/register', ['name' => 'Someone', 'mobile' => '9500000001'])->assertSessionHasErrors('mobile');
        $this->assertSame([], $this->sms->sent);
    }

    #[Test]
    public function invalid_mobile_numbers_are_refused(): void
    {
        $this->post('/register', ['name' => 'Someone', 'mobile' => '12345'])->assertSessionHasErrors('mobile');
    }

    #[Test]
    public function otp_resend_has_a_cooldown(): void
    {
        $this->startRegistration();
        $this->post('/register', ['name' => 'Asha Kumari', 'mobile' => self::MOBILE])->assertSessionHasErrors('mobile');
        $this->assertCount(1, $this->sms->sent);
    }

    #[Test]
    public function the_password_policy_applies_to_citizens(): void
    {
        $this->startRegistration();
        $this->post('/register/verify', [
            'otp' => $this->sms->lastCodeFor(self::MOBILE), 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    #[Test]
    public function password_reset_by_otp_does_not_reveal_unknown_numbers(): void
    {
        $this->post('/forgot-password', ['mobile' => '9999999999'])->assertRedirect('/reset-password');
        $this->assertSame([], $this->sms->sent);

        $this->post('/forgot-password', ['mobile' => '9500000002'])->assertRedirect('/reset-password');
        $this->post('/reset-password', [
            'otp' => $this->sms->lastCodeFor('9500000002'), 'password' => 'Reset#Pass2026', 'password_confirmation' => 'Reset#Pass2026',
        ])->assertRedirect('/login');

        $this->post('/login', ['identifier' => '9500000002', 'password' => 'Reset#Pass2026'])->assertRedirect('/dashboard');
    }
}
