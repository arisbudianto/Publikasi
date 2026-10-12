<?php

namespace Tests\Feature;

use App\Models\SupportPayment;
use App\Models\User;
use App\Services\OperationalHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config(['payments.enabled' => true, 'payments.instructions' => 'Kanal pembayaran resmi dipilih operator.']);
    }

    private function createPayment(): SupportPayment
    {
        $this->enable();
        $this->actingAs(User::factory()->create());
        $this->post('/akun/pembayaran', ['amount_idr' => 10000, 'acknowledge' => 1])->assertRedirect();

        return SupportPayment::first();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    public function test_disabled_and_incomplete_configuration_fail_closed_without_gating_catalog(): void
    {
        config(['payments.enabled' => false]);
        $this->get('/layanan')->assertOk()->assertSee('belum diaktifkan')->assertDontSee('APP_KEY');
        $this->get('/')->assertOk();
        $this->getJson('/akun/pembayaran')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get('/akun/pembayaran')->assertOk()->assertSee('Tidak perlu mengirim dana');
        $this->postJson('/akun/pembayaran', ['amount_idr' => 10000, 'acknowledge' => 1])->assertStatus(503);
        config(['payments.enabled' => true, 'payments.instructions' => '']);
        $this->postJson('/akun/pembayaran', ['amount_idr' => 10000, 'acknowledge' => 1])->assertStatus(503);
        $this->assertDatabaseCount('support_payments', 0);
    }

    public function test_integer_amount_validation_duplicate_intent_and_owner_cancel(): void
    {
        $this->enable();
        $this->actingAs(User::factory()->create());
        $this->post('/akun/pembayaran', ['amount_idr' => 10000.5, 'acknowledge' => 1])->assertSessionHasErrors('amount_idr');
        $this->post('/akun/pembayaran', ['amount_idr' => 10000])->assertSessionHasErrors('acknowledge');
        $this->post('/akun/pembayaran', ['amount_idr' => 10000, 'acknowledge' => 1, 'status' => 'verified'])->assertRedirect();
        $p = SupportPayment::first();
        $this->assertSame('pending', $p->status);
        $this->assertSame(10000, $p->amount_idr);
        $this->post('/akun/pembayaran', ['amount_idr' => 20000, 'acknowledge' => 1])->assertStatus(409);
        $this->post('/akun/pembayaran/'.$p->id.'/batal', ['version' => 2])->assertStatus(409);
        $this->post('/akun/pembayaran/'.$p->id.'/batal', ['version' => 1])->assertRedirect();
        $this->assertSame('cancelled', $p->fresh()->status);
        $this->assertDatabaseCount('support_payment_events', 2);
        $this->post('/akun/pembayaran/'.$p->id.'/batal', ['version' => 2])->assertStatus(409);
    }

    public function test_other_accounts_cannot_read_cancel_or_verify(): void
    {
        $p = $this->createPayment();
        $this->actingAs(User::factory()->create());
        $this->get('/akun/pembayaran')->assertDontSee('Permintaan #'.$p->id);
        $this->post('/akun/pembayaran/'.$p->id.'/batal', ['version' => 1])->assertNotFound();
        $this->get('/admin/pembayaran')->assertForbidden();
        $this->post('/admin/pembayaran/'.$p->id.'/verifikasi', ['version' => 1, 'status' => 'verified', 'note' => 'Verified through official channel', 'confirm' => 1])->assertForbidden();
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_admin_verification_requires_evidence_confirmation_and_is_idempotent(): void
    {
        $p = $this->createPayment();
        $this->actingAs($this->admin());
        $url = '/admin/pembayaran/'.$p->id.'/verifikasi';
        $this->get('/admin/pembayaran')->assertOk()->assertSee('Operator #');
        $data = ['version' => 1, 'status' => 'verified', 'note' => 'Verified through official channel'];
        $this->post($url, $data)->assertSessionHasErrors('confirm');
        $this->post($url, $data + ['confirm' => 1])->assertRedirect();
        $this->post($url, $data + ['confirm' => 1])->assertStatus(409);
        $this->assertSame('verified', $p->fresh()->status);
        $this->assertDatabaseCount('support_payment_events', 2);
        $this->get('/akun/pembayaran')->assertDontSee('Permintaan #'.$p->id);
        $this->actingAs(User::find($p->user_id))->get('/akun/pembayaran')->assertSee('Verified through official channel');
    }

    public function test_operational_checks_are_private_and_never_expose_keys(): void
    {
        config(['app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://example.org', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'semantic.key' => 'PRIVATE_AI_KEY', 'semantic.enabled' => true]);
        $this->getJson('/admin/operasional')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get('/admin/operasional')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/operasional')->assertOk()->assertSee('database')->assertDontSee('PRIVATE_APP_KEY')->assertDontSee('PRIVATE_AI_KEY')->assertHeader('Cache-Control', 'no-store, private');
        $health = app(OperationalHealth::class)->inspect();
        $this->assertSame('PASS', $health['checks']['database']);
        $this->assertSame('PASS', $health['checks']['schema']);
        $this->assertSame('PASS', $health['checks']['cache']);
        config(['app.debug' => true, 'semantic.key' => '']);
        $health = app(OperationalHealth::class)->inspect();
        $this->assertFalse($health['healthy']);
        $this->assertSame('FAIL', $health['checks']['debug_off']);
        $this->assertSame('FAIL', $health['checks']['ai_ready']);
        $this->artisan('publikasi:health --json')->assertExitCode(1);
    }

    public function test_probe_failures_return_safe_status_without_exception_details(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('PRIVATE_DB_PASSWORD'));
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('PRIVATE_CACHE_PASSWORD'));
        Cache::shouldReceive('forget')->once()->andReturn(true);
        $health = app(OperationalHealth::class)->inspect();
        $this->assertSame('FAIL', $health['checks']['database']);
        $this->assertSame('FAIL',$health['checks']['cache']);
        $this->assertStringNotContainsString('PRIVATE_',json_encode($health));
    }
}
