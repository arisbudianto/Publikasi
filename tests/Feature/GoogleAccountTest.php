<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAccountTest extends TestCase
{
    use RefreshDatabase;

    private function start(): string
    {
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
        $response = $this->get(route('account.google.redirect'))->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $params);
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertSame(route('account.google.callback'), $params['redirect_uri']);

        return $params['state'];
    }

    private function google(string $email = 'reader@gmail.com', string $sub = 'google-sub-1'): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => 'Reader'], 200),
        ]);
    }

    public function test_google_registration_and_replay_protection(): void
    {
        $state = $this->start();
        $this->google();
        $this->get(route('account.google.callback', ['code' => 'valid', 'state' => 'wrong']))->assertRedirect(route('account.login'));
        $this->assertDatabaseCount('users', 0);
        $state = $this->start();
        $this->get(route('account.google.callback', ['code' => 'valid', 'state' => $state]))->assertRedirect(route('account.index'));
        $user = User::first();
        $this->assertSame('google-sub-1', $user->google_sub);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_admin);
        $this->get(route('account.google.callback', ['code' => 'valid', 'state' => $state]))->assertRedirect(route('account.login'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_same_email_is_not_automatically_linked_and_owner_can_link(): void
    {
        $user = User::factory()->create(['email' => 'reader@gmail.com']);
        $state = $this->start();
        $this->google();
        $this->get(route('account.google.callback', ['code' => 'valid', 'state' => $state]))->assertRedirect(route('account.login'));
        $this->assertNull($user->fresh()->google_sub);
        $this->actingAs($user);
        $state = $this->start();
        $this->get(route('account.google.callback', ['code' => 'valid', 'state' => $state]))->assertRedirect(route('account.index'));
        $this->assertSame('google-sub-1', $user->fresh()->google_sub);
    }

    public function test_google_unavailable_without_configuration(): void
    {
        $this->get(route('account.google.redirect'))->assertStatus(503);
        $this->get(route('account.register'))->assertDontSee('Lanjutkan dengan Google');
    }
}
