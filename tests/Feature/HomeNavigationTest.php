<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_and_pages_use_top_navigation(): void
    {
        $this->get('/')->assertOk()->assertSee('Cari jurnal')->assertSee('Fitur')
            ->assertDontSee('id="site-sidebar"', false);
        $this->get('/sumber-jurnal')->assertOk()->assertSee('Database jurnal')
            ->assertDontSee('id="site-sidebar"', false);
        $this->get('/akun/login')->assertOk()->assertDontSee('id="site-sidebar"', false);
    }

    public function test_signed_in_user_gets_left_navigation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertRedirect(route('account.index'));
        $this->get('/akun')->assertOk()->assertSee('id="site-sidebar"', false)
            ->assertSee('Usulan saya');
    }
}
