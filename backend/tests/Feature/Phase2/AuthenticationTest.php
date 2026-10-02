<?php

namespace Tests\Feature\Phase2;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;

class AuthenticationTest extends SeededTestCase
{
    private const PASSWORD = 'Rcd@Demo2026';

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public static function identifiers(): array
    {
        return ['email' => ['je001@rcd.test'], 'mobile' => ['9100000003'], 'employee code' => ['je001']];
    }

    #[Test]
    #[DataProvider('identifiers')]
    public function staff_sign_in_with_email_mobile_or_employee_code(string $identifier): void
    {
        $this->post('/login', ['identifier' => $identifier, 'password' => self::PASSWORD])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->userByEmail('je001@rcd.test'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $this->userByEmail('je001@rcd.test')->id]);
        $this->get('/dashboard')->assertOk()->assertSee('JE001');
    }

    #[Test]
    public function wrong_password_is_rejected_and_audited(): void
    {
        $this->post('/login', ['identifier' => 'je001@rcd.test', 'password' => 'wrong'])->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
    }

    #[Test]
    public function repeated_failures_lock_the_account_even_for_the_right_password(): void
    {
        $max = app(Settings::class)->int('auth.max_login_attempts');
        for ($i = 0; $i < $max; $i++) {
            $this->post('/login', ['identifier' => 'je002@rcd.test', 'password' => 'wrong']);
        }

        $this->post('/login', ['identifier' => 'je002@rcd.test', 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['identifier' => 'Too many failed attempts. Try again in 15 minute(s).']);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.locked_out']);
    }

    #[Test]
    public function suspended_users_cannot_sign_in(): void
    {
        $this->userByEmail('je003@rcd.test')->forceFill(['status' => 'suspended'])->saveQuietly();

        $this->post('/login', ['identifier' => 'je003@rcd.test', 'password' => self::PASSWORD])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    #[Test]
    public function users_suspended_mid_session_are_signed_out(): void
    {
        $user = $this->userByEmail('je004@rcd.test');
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['status' => 'suspended'])->saveQuietly();

        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect('/login');
    }

    #[Test]
    public function staff_with_an_admin_set_password_must_change_it_first(): void
    {
        $user = $this->userByEmail('je005@rcd.test');
        $user->forceFill(['password_changed_at' => null])->saveQuietly();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/change-password');

        $this->put('/change-password', [
            'current_password' => self::PASSWORD,
            'password' => 'Fresh#Pass2026',
            'password_confirmation' => 'Fresh#Pass2026',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed', 'auditable_id' => $user->id]);
    }

    #[Test]
    public function expired_staff_passwords_force_a_change_but_citizens_are_exempt(): void
    {
        $staff = $this->userByEmail('je006@rcd.test');
        $staff->forceFill(['password_changed_at' => now()->subDays(365)])->saveQuietly();
        $citizen = User::where('mobile', '9500000001')->firstOrFail();
        $citizen->forceFill(['password_changed_at' => now()->subDays(365)])->saveQuietly();

        $this->actingAs($staff)->get('/dashboard')->assertRedirect('/change-password');
        $this->actingAs($citizen)->get('/dashboard')->assertOk();
    }

    #[Test]
    public function weak_new_passwords_are_rejected(): void
    {
        $this->actingAs($this->userByEmail('je007@rcd.test'))
            ->put('/change-password', ['current_password' => self::PASSWORD, 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password');
    }

    #[Test]
    public function logout_ends_the_session_and_is_audited(): void
    {
        $user = $this->userByEmail('je008@rcd.test');
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.logout')->where('user_id', $user->id)->exists());
    }
}
