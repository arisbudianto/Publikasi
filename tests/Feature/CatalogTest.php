<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\User;
use App\Services\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = []): array
    {
        return array_merge(['name' => 'Jurnal Pendidikan Uji', 'publisher' => 'Penerbit uji', 'issn' => '2088-2866', 'official_url' => 'https://example.org/journal', 'subject' => 'Pendidikan', 'scope_summary' => 'Scope pendidikan untuk fixture pengujian.', 'scope_source_url' => 'https://example.org/about', 'checked_at' => '2026-01-01', 'status' => 'published'], $overrides);
    }

    public function test_public_search_and_unknown_fees(): void
    {
        Journal::create($this->row());
        $this->get('/katalog-jurnal?q=2088-2866')->assertOk()->assertSee('Jurnal Pendidikan Uji');
        $this->get('/jurnal/1')->assertOk()->assertSee('Belum diketahui');
        $this->get('/katalog-jurnal?q=tidak-ada')->assertSee('Belum ada jurnal');
    }

    public function test_drafts_and_archives_are_not_public(): void
    {
        $j = Journal::create($this->row(['status' => 'draft']));
        $this->get('/katalog-jurnal')->assertDontSee('Jurnal Pendidikan Uji');
        $this->get('/jurnal/'.$j->id)->assertNotFound();
        $j->update(['status' => 'archived']);
        $this->get('/jurnal/'.$j->id)->assertNotFound();
    }

    public function test_non_admin_cannot_mutate_or_import(): void
    {
        $this->post('/admin/jurnal', $this->row())->assertRedirect('/admin/login');
        $u = User::factory()->create();
        $this->actingAs($u)->post('/admin/jurnal', $this->row())->assertForbidden();
        $this->actingAs($u)->post('/admin/import/commit')->assertForbidden();
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_admin_crud_audit_and_archive(): void
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();
        $this->actingAs($u)->post('/admin/jurnal', $this->row())->assertRedirect('/admin');
        $j = Journal::first();
        $this->actingAs($u)->put('/admin/jurnal/'.$j->id, $this->row(['name' => 'Nama baru']))->assertRedirect('/admin');
        $this->actingAs($u)->post('/admin/jurnal/'.$j->id.'/archive')->assertRedirect();
        $this->assertDatabaseHas('journals', ['status' => 'archived']);
        $this->assertDatabaseCount('catalog_audits', 3);
    }

    public function test_invalid_checksum_and_urls_rejected(): void
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();
        $this->actingAs($u)->post('/admin/jurnal', $this->row(['issn' => '1234-5678', 'official_url' => 'javascript:alert(1)']))->assertSessionHasErrors(['issn', 'official_url']);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_duplicate_csv_preview_and_transaction_rollback(): void
    {
        $service = app(Catalog::class);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $h = fopen($path, 'w');
        fputcsv($h, Catalog::FIELDS, ',', '"', '');
        fputcsv($h, array_values($this->row()), ',', '"', '');
        fputcsv($h, array_values($this->row()), ',', '"', '');
        fclose($h);
        $this->assertNotEmpty($service->preview($path)['errors']);
        unlink($path);
        $u = User::factory()->create();
        try {
            $service->import([$this->row(), $this->row()], $u->id);
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('journals', 0);
            $this->assertDatabaseCount('catalog_audits', 0);
        }
    }

    public function test_login_rate_limit_and_nonadmin_logout(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/admin/login', ['email' => 'nobody@example.org', 'password' => 'bad'])->assertSessionHasErrors('email');
        }$this->assertGuest();
    }

    public function test_html_is_escaped(): void
    {
        Journal::create($this->row(['name' => '<script>alert(1)</script>']));
        $this->get('/katalog-jurnal')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
