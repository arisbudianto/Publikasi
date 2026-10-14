<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_signed_email_verification_when_mail_is_enabled(): void
    {
        config(['account.email_enabled' => true]);
        Notification::fake();
        $this->post('/akun/daftar', ['name' => 'Pembaca', 'email' => 'pembaca@example.org', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'])
            ->assertRedirect(route('verification.notice'));
        $user = User::first();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertNull($user->email_verified_at);
        $this->get('/akun')->assertRedirect(route('verification.notice'));
        $this->post('/akun/pantauan', [])->assertRedirect(route('verification.notice'));
        $this->get('/akun/verifikasi-email/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(route('account.index'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get('/akun')->assertOk();
    }

    public function test_reset_token_changes_password_and_unknown_email_has_generic_response(): void
    {
        config(['account.email_enabled' => true]);
        Notification::fake();
        $user = User::factory()->create(['email' => 'pembaca@example.org', 'password' => 'StrongPassword123']);
        $this->post('/akun/lupa-password', ['email' => 'missing@example.org'])->assertRedirect()->assertSessionHas('success');
        $this->post('/akun/lupa-password', ['email' => 'pembaca@example.org'])->assertRedirect()->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->post('/akun/reset-password', ['token' => 'invalid', 'email' => $user->email, 'password' => 'AnotherStrongPass123', 'password_confirmation' => 'AnotherStrongPass123'])->assertSessionHasErrors('email');
        $this->post('/akun/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'AnotherStrongPass123', 'password_confirmation' => 'AnotherStrongPass123'])->assertRedirect(route('account.login'));
        $this->post('/akun/login', ['email' => $user->email, 'password' => 'AnotherStrongPass123'])->assertRedirect(route('account.index'));
    }

    public function test_mail_flows_fail_closed_when_delivery_is_unavailable(): void
    {
        config(['account.email_enabled' => false]);
        $this->get('/akun/lupa-password')->assertOk()->assertSee('belum tersedia');
        $this->post('/akun/lupa-password', ['email' => 'x@example.org'])->assertStatus(503);
        $this->get('/akun/login')->assertSee('Lupa password?');
    }
}
