<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurationTest extends TestCase
{
    use RefreshDatabase;

    private function journal(string $issn = '2088-2866'): Journal
    {
        return Journal::create(['name' => 'Jurnal Kurasi', 'publisher' => 'Uji', 'issn' => $issn, 'subject' => 'Vokasi', 'scope_summary' => 'Scope vokasi', 'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org', 'checked_at' => today(), 'status' => 'published']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    private function data(): array
    {
        return ['kind' => 'fee', 'label' => 'APC', 'value' => 'USD 100, akses terbuka', 'verification' => 'publisher_claim', 'source_url' => 'https://example.org/apc', 'checked_at' => today()->format('Y-m-d'), 'period' => '2026', 'review_note' => 'Diperiksa tabel biaya pada halaman resmi penerbit.', 'confirmed' => '1'];
    }

    public function test_curation_opens_all_journals_and_shows_new_drafts(): void
    {
        $draft = $this->journal();
        $draft->update(['status' => 'draft']);
        $this->actingAs($this->admin())
            ->get('/admin/kurasi')
            ->assertOk()
            ->assertSee('1 draft')
            ->assertSee('Draft · belum publik')
            ->assertSee('Tinjau dan terbitkan')
            ->assertSee(route('admin.edit', $draft));
        $this->get('/admin/kurasi?review=due')->assertOk()->assertDontSee('Jurnal Kurasi');
    }

    public function test_non_admin_cannot_review_publish_or_retire(): void
    {
        $j = $this->journal();
        $this->get('/admin/kurasi')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/kurasi/'.$j->id)->assertForbidden();
        $this->post('/admin/kurasi/'.$j->id.'/observasi', $this->data())->assertForbidden();
        $this->post('/admin/kurasi/'.$j->id.'/observasi/1/tarik', ['reason' => 'Tidak berlaku lagi'])->assertForbidden();
        $this->assertDatabaseCount('journal_observations', 0);
    }

    public function test_new_version_preserves_history_and_public_only_latest(): void
    {
        $j = $this->journal();
        $u = $this->admin();
        $this->actingAs($u)->post('/admin/kurasi/'.$j->id.'/observasi', $this->data())->assertRedirect();
        $new = $this->data();
        $new['value'] = 'USD 150, akses terbuka';
        $new['verification'] = 'verified';
        $this->post('/admin/kurasi/'.$j->id.'/observasi', $new)->assertRedirect();
        $this->assertDatabaseCount('journal_observations', 2);
        $this->assertSame(1, $j->observations()->count());
        $this->assertSame(2, $j->allObservations()->count());
        $this->assertNotNull($j->allObservations()->first()->retired_at);
        $this->assertDatabaseCount('catalog_audits', 2);
        $this->get('/jurnal/'.$j->id)->assertSee('USD 150')->assertDontSee('USD 100');
        $this->get('/bandingkan?ids[]='.$j->id)->assertSee('USD 150')->assertDontSee('USD 100');
        $this->get('/admin/kurasi/'.$j->id)->assertOk()->assertSee('USD 100')->assertSee('USD 150')->assertSee('Diperiksa tabel biaya');
    }

    public function test_retirement_keeps_audit_and_unknown_filter_is_correct(): void
    {
        $j = $this->journal();
        $this->actingAs($this->admin())->post('/admin/kurasi/'.$j->id.'/observasi', $this->data());
        $id = $j->observations()->first()->id;
        $this->post('/admin/kurasi/'.$j->id.'/observasi/'.$id.'/tarik', ['reason' => 'Biaya telah berubah dan belum diketahui'])->assertRedirect();
        $this->assertDatabaseCount('journal_observations', 1);
        $this->assertSame(0, $j->observations()->count());
        $this->get('/katalog-terkurasi?information=fee&availability=known')->assertDontSee('Jurnal Kurasi');
        $this->get('/katalog-terkurasi?information=fee&availability=unknown')->assertSee('Jurnal Kurasi');
        $this->assertSame('observation_retired', DB::table('catalog_audits')->latest('id')->first()->action);
    }

    public function test_invalid_sources_future_dates_and_missing_confirmation_rejected(): void
    {
        $j = $this->journal();
        $this->actingAs($this->admin());
        $d = $this->data();
        $d['source_url'] = 'javascript:alert(1)';
        $d['checked_at'] = today()->addDay()->format('Y-m-d');
        $d['confirmed'] = '0';
        $d['review_note'] = 'short';
        $this->post('/admin/kurasi/'.$j->id.'/observasi', $d)->assertSessionHasErrors(['source_url', 'checked_at', 'confirmed', 'review_note']);
        $this->assertDatabaseCount('journal_observations', 0);
    }

    public function test_retirement_cannot_target_another_journal(): void
    {
        $a = $this->journal();
        $b = $this->journal('2339-1286');
        $this->actingAs($this->admin())->post('/admin/kurasi/'.$b->id.'/observasi', $this->data());
        $id = $b->observations()->first()->id;
        $this->post('/admin/kurasi/'.$a->id.'/observasi/'.$id.'/tarik', ['reason' => 'Salah relasi jurnal'])->assertNotFound();
        $this->assertSame(1, $b->observations()->count());
    }

    public function test_review_due_claims_and_public_staleness(): void
    {
        $j = $this->journal();
        $j->update(['checked_at' => today()->subDays(181)]);
        $this->actingAs($this->admin())->get('/admin/kurasi')->assertSee('Jurnal Kurasi');
        $this->get('/jurnal/'.$j->id)->assertSee('lebih dari 180 hari');
        $this->post('/admin/kurasi/'.$j->id.'/observasi', $this->data());
        $this->get('/admin/kurasi?review=claims')->assertSee('Jurnal Kurasi');
        $this->get('/admin/kurasi?review=bad')->assertSessionHasErrors('review');
    }

    public function test_crossref_refresh_is_admin_only_scoped_and_audited(): void
    {
        $j = $this->journal();
        Http::fake(['api.crossref.org/*' => Http::response(['message' => ['items' => []]])]);
        $this->actingAs(User::factory()->create())->post('/admin/kurasi/'.$j->id.'/artikel/refresh')->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs($this->admin())->post('/admin/kurasi/'.$j->id.'/artikel/refresh')->assertRedirect();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('catalog_audits', ['action' => 'articles_refreshed', 'journal_id' => $j->id]);
        $this->assertDatabaseCount('journal_observations', 0);
    }
}
