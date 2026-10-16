<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalSource;
use App\Models\User;
use App\Services\SintaDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SintaDatasetTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_dataset_import_preserves_catalog_and_is_idempotent(): void
    {
        $service = app(SintaDataset::class);
        $p = $service->preview();
        $this->assertSame(999, count($p['rows']));
        $this->assertSame(308, $p['manifest']['education']);
        $this->assertSame(75, $p['manifest']['unnamed']);
        $this->assertSame(691, $p['manifest']['total'] - $p['manifest']['education']);
        $this->assertSame(['created' => 999, 'skipped' => 0], $service->import());
        $this->assertSame(['created' => 0, 'skipped' => 999], $service->import());
        $this->assertDatabaseCount('journals', 0);
        $this->assertDatabaseCount('journal_observations', 0);
        $this->assertSame(308, JournalSource::where('education', true)->count());
        $this->assertSame(691, JournalSource::where('education', false)->count());
        $this->get('/sumber-jurnal')->assertOk()->assertSee('999 rekaman sesuai pencarian')->assertSee('308 berlabel Pendidikan')->assertSee('691 bidang lainnya')->assertSee('bukan status terkini');
        $this->get('/')->assertOk()->assertSee('999')->assertSee('keputusan akreditasi resmi 24 Juli 2026')->assertSee('Cari jurnal')->assertDontSee('id="site-sidebar"', false);
        $this->get('/katalog-jurnal')->assertOk()->assertSee('jurnal yang sudah memiliki cakupan');
        $this->get('/sumber-jurnal?q=24423904')->assertSee('Jurnal Pendidikan Sains');
        $this->get('/sumber-jurnal?q=Problem-Based%20Learning')->assertSee('Jurnal Pendidikan Sains');
        $this->get('/sumber-jurnal?q=978')->assertSee('Padjadjaran Journal of Dentistry');
        $unnamed = JournalSource::where('source_id', 15)->first();
        $this->get('/sumber-jurnal?q=15')->assertSee('Nama jurnal tidak tersedia (SINTA #15)');
        $this->get('/sumber-jurnal/'.$unnamed->id)->assertOk()->assertSee('Nama jurnal tidak tersedia (SINTA #15)');
        $this->get('/sumber-jurnal/'.JournalSource::where('source_id', 1)->first()->id)->assertSee('belum tersinkron')->assertSee('20/03/2025');
    }

    private function source(): JournalSource
    {
        return JournalSource::create(['source_id' => 1, 'name' => 'Source <script>alert(1)</script>', 'publisher' => 'Uji', 'education' => true, 'p_issn' => '2088-2866', 'source_url' => 'https://sinta.kemdikbud.go.id/journals/profile/1', 'captured_at' => '2025-03-20', 'raw_identifiers' => [], 'article_titles' => ['<script>bad</script>'], 'dataset_sha256' => str_repeat('a', 64)]);
    }

    private function data(): array
    {
        return ['name' => 'Draft Uji', 'publisher' => 'Uji', 'issn' => '2088-2866', 'subject' => 'Pendidikan', 'official_url' => 'https://example.org', 'scope_source_url' => 'https://example.org/scope', 'scope_summary' => 'Ringkasan resmi sudah diperiksa administrator.', 'checked_at' => today()->format('Y-m-d'), 'status' => 'published', 'confirm' => 1];
    }

    public function test_admin_confirmation_creates_only_draft_and_repeat_does_not_overwrite(): void
    {
        $s = $this->source();
        $url = '/admin/sumber-jurnal/'.$s->id.'/draft';
        $this->get('/sumber-jurnal/'.$s->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
        $this->postJson($url, $this->data())->assertUnauthorized();
        $u = User::factory()->create();
        $this->actingAs($u)->post($url, $this->data())->assertForbidden();
        $u->is_admin = true;
        $u->save();
        $this->get('/admin/sumber-jurnal/'.$s->id)->assertOk();
        $d = $this->data();
        unset($d['confirm']);
        $this->post($url, $d)->assertSessionHasErrors('confirm');
        $this->post($url, $this->data())->assertRedirect();
        $j = Journal::first();
        $this->assertSame('draft', $j->status);
        $this->post($url, $this->data())->assertStatus(409);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('catalog_audits', 1);
    }

    public function test_snapshot_with_another_fingerprint_cannot_be_overwritten(): void
    {
        $s = $this->source();
        $this->expectException(\RuntimeException::class);
        app(SintaDataset::class)->import();
    }

    public function test_non_education_sources_are_public_and_can_be_promoted_only_as_reviewed_drafts(): void
    {
        $s = JournalSource::create(['source_id' => 42, 'name' => 'Journal of Regional History', 'publisher' => 'Institute of History', 'subject' => 'History', 'education' => false, 'source_url' => 'https://sinta.kemdikbud.go.id/journals/profile/42', 'captured_at' => '2025-03-20', 'raw_identifiers' => [], 'article_titles' => ['Historical archives and public memory'], 'dataset_sha256' => str_repeat('b', 64)]);
        $this->get('/sumber-jurnal')->assertOk()->assertSee('Journal of Regional History')->assertSee('Bidang lainnya');
        $this->get('/sumber-jurnal/'.$s->id)->assertOk()->assertSee('Journal of Regional History')->assertSee('History');

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin)->get('/admin/sumber-jurnal/'.$s->id)->assertOk()->assertSee('Bidang/subjek');
        $data = $this->data();
        $data['name'] = 'Journal of Regional History';
        $data['subject'] = 'History';
        $this->post('/admin/sumber-jurnal/'.$s->id.'/draft', $data)->assertRedirect();
        $this->assertSame('draft', Journal::first()->status);
        $this->assertSame('History', Journal::first()->subject);
    }

    public function test_existing_issn_is_linked_for_review_not_created_as_duplicate(): void
    {
        $s = $this->source();
        Journal::create(['name' => 'Existing', 'publisher' => 'Uji', 'issn' => '2088-2866', 'official_url' => 'https://example.org', 'scope_summary' => 'Scope jurnal yang sudah ditinjau secara manual', 'subject' => 'Pendidikan', 'scope_source_url' => 'https://example.org/scope', 'checked_at' => today(), 'status' => 'published']);
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $this->get('/admin/sumber-jurnal/'.$s->id)->assertOk()->assertSee('Existing')->assertSee('Draft duplikat tidak dibuat');
        $this->post('/admin/sumber-jurnal/'.$s->id.'/draft', $this->data())->assertStatus(409);
        $this->assertDatabaseCount('journals', 1);
        $this->assertNull($s->fresh()->journal_id);
    }
}
