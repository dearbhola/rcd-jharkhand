<?php

namespace Tests\Feature\Phase2;

use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

/**
 * SMS-OTP features are parked (no SMS provider yet): routes 404 and links are hidden.
 */
class SmsOtpParkedTest extends SeededTestCase
{
    #[Test]
    public function otp_routes_are_unavailable_by_default(): void
    {
        foreach (['/register', '/register/verify', '/forgot-password', '/reset-password'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/register', ['name' => 'X', 'mobile' => '9876500009'])->assertNotFound();
    }

    #[Test]
    public function the_login_page_hides_registration_and_reset_links(): void
    {
        $this->get('/login')->assertOk()
            ->assertDontSee('Register with your mobile number')
            ->assertDontSee('Forgot password?')
            ->assertSee('Contact your RCD office administrator');
    }
}
