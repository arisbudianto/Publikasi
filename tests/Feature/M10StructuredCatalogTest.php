<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalEmbedding;
use App\Services\JournalAccreditationLinker;
use App\Models\User;
use App\Services\SemanticMatcher;
use App\Services\StructuredObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M10StructuredCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function journal(string $issn = '2088-2866'): Journal
    {
        return Journal::create([
            'name' => 'Jurnal Kurasi M10', 'publisher' => 'Penerbit Uji', 'issn' => $issn,
            'subject' => 'Vokasi', 'scope_summary' => 'Pelatihan kejuruan', 'official_url' => 'https://example.org',
            'scope_source_url' => 'https://example.org/scope', 'checked_at' => today(), 'status' => 'published',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->update(['is_admin' => true]);

        return $user;
    }

    private function row(string $issn = '2088-2866', array $replace = []): array
    {
        return array_replace([
            'journal_issn' => $issn, 'kind' => 'fee', 'label' => 'APC akses terbuka', 'value' => 'Gratis sesuai halaman biaya',
            'verification' => 'verified', 'source_url' => 'https://example.org/fees', 'checked_at' => today()->format('Y-m-d'),
            'period' => '2026', 'metric_scheme' => '', 'rank_value' => '', 'amount_minor' => '0', 'currency' => 'IDR',
            'fee_route' => 'apc_oa', 'waiver_available' => '1', 'language' => '', 'frequency_per_year' => '',
            'effective_from' => '', 'effective_to' => '', 'review_note' => 'Halaman biaya resmi menyatakan tidak ada APC untuk jalur ini.',
        ], $replace);
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, StructuredObservation::FIELDS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($field) => $row[$field] ?? '', StructuredObservation::FIELDS), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function test_structured_import_previews_then_applies_all_rows_with_history(): void
    {
        $journal = $this->journal();
        $admin = $this->admin();
        $old = $journal->allObservations()->create([
            'kind' => 'fee', 'label' => 'APC akses terbuka', 'value' => 'Rp100.000', 'verification' => 'publisher_claim',
            'source_url' => 'https://example.org/old', 'checked_at' => today(), 'review_note' => 'Nilai lama untuk uji riwayat.',
        ]);

        $this->actingAs($admin)->post(route('admin.observation-import.preview'), [
            'csv' => UploadedFile::fake()->createWithContent('observations.csv', $this->csv([$this->row()])),
        ])->assertOk()->assertSee('1 baris valid')->assertSee('Terapkan semua baris');
        $this->assertDatabaseCount('journal_observations', 1);

        $this->post(route('admin.observation-import.commit'))->assertRedirect(route('admin.curation'));
        $this->assertDatabaseCount('journal_observations', 2);
        $this->assertNotNull($old->fresh()->retired_at);
        $this->assertDatabaseHas('journal_observations', [
            'journal_id' => $journal->id, 'amount_minor' => 0, 'currency' => 'IDR', 'fee_route' => 'apc_oa', 'waiver_available' => 1,
        ]);
        $this->assertDatabaseHas('catalog_audits', ['action' => 'observation_published', 'journal_id' => $journal->id]);
    }

    public function test_invalid_csv_does_not_create_preview_or_write_partial_rows(): void
    {
        $this->journal();
        $this->actingAs($this->admin())->post(route('admin.observation-import.preview'), [
            'csv' => UploadedFile::fake()->createWithContent('observations.csv', $this->csv([
                $this->row(), $this->row('2088-2866', ['amount_minor' => '', 'currency' => '', 'fee_route' => '']),
            ])),
        ])->assertOk()->assertSee('Impor belum dapat diterapkan');
        $this->post(route('admin.observation-import.commit'))->assertStatus(422);
        $this->assertDatabaseCount('journal_observations', 0);
    }

    public function test_observation_import_is_admin_only(): void
    {
        $this->journal();
        $this->actingAs(User::factory()->create())->get(route('admin.observation-import.form'))->assertForbidden();
        $this->assertDatabaseCount('journal_observations', 0);
    }

    public function test_additional_issn_is_unique_audited_searchable_and_retired_not_deleted(): void
    {
        $journal = $this->journal();
        $other = $this->journal('2339-1286');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.identifier.store', $journal), [
            'type' => 'electronic_issn', 'value' => ' 2339-1286 ', 'source_url' => 'https://example.org/issn', 'checked_at' => today()->format('Y-m-d'),
        ])->assertSessionHasErrors('value');
        $this->assertDatabaseCount('journal_identifiers', 0);

        $this->post(route('admin.identifier.store', $journal), [
            'type' => 'print_issn', 'value' => '2049-3630', 'source_url' => 'https://example.org/issn', 'checked_at' => today()->format('Y-m-d'),
        ])->assertRedirect();
        $identifier = $journal->identifiers()->firstOrFail();
        $this->get('/katalog-terkurasi?q=2049-3630')->assertSee($journal->name);
        $this->assertDatabaseHas('catalog_audits', ['action' => 'identifier_added', 'journal_id' => $journal->id]);

        $this->post(route('admin.identifier.retire', [$journal, $identifier->id]), ['reason' => 'Identitas tidak lagi berlaku menurut sumber resmi.'])->assertRedirect();
        $this->assertDatabaseCount('journal_identifiers', 1);
        $this->assertNotNull($identifier->fresh()->retired_at);
        $this->get('/katalog-terkurasi?q=2049-3630')->assertDontSee($journal->name);
        $this->assertDatabaseHas('catalog_audits', ['action' => 'identifier_retired', 'journal_id' => $journal->id]);
        $this->assertSame('2339-1286', $other->issn);
    }

    public function test_scope_embeddings_are_binary_persisted_reused_and_refreshed_after_scope_change(): void
    {
        $journal = $this->journal();
        config(['semantic.enabled' => true, 'semantic.key' => 'FAKE_TEST_KEY', 'semantic.catalog_limit' => 50]);
        Http::preventStrayRequests();
        $scopeCalls = 0;
        Http::fake([
            'ai.dinoiki.com/*' => function ($request) use (&$scopeCalls) {
                if (in_array("Vokasi\nPelatihan kejuruan", $request['input'], true)) {
                    $scopeCalls++;
                }

                return Http::response(['data' => array_map(fn ($i) => ['index' => $i, 'embedding' => [1, 0]], array_keys($request['input']))]);
            },
            'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        ]);

        $matcher = app(SemanticMatcher::class);
        $matcher->match('Judul uji', str_repeat('abstrak penelitian ', 8), '');
        $first = JournalEmbedding::firstOrFail();
        $this->assertSame([1.0, 0.0], $first->decodedVector());
        $matcher->match('Judul lain', str_repeat('abstrak penelitian ', 8), '');
        $this->assertSame(1, $scopeCalls);
        $this->assertDatabaseCount('journal_embeddings', 1);

        $journal->update(['scope_summary' => 'Metode pembelajaran praktik industri']);
        $matcher->match('Judul ketiga', str_repeat('abstrak penelitian ', 8), '');
        $this->assertSame(2, $scopeCalls);
        $this->assertNotSame($first->scope_hash, $first->fresh()->scope_hash);
        $this->assertDatabaseCount('journal_embeddings', 1);
    }

    public function test_embedding_binary_round_trip_and_structured_currency_normalization(): void
    {
        $embedding = new JournalEmbedding(['dimensions' => 3, 'vector' => JournalEmbedding::encode([1, -0.5, 0.25])]);
        $this->assertSame([1.0, -0.5, 0.25], $embedding->decodedVector());
        $this->assertSame('IDR', StructuredObservation::normalize(['currency' => 'idr'])['currency']);
    }

    public function test_sinta_linker_previews_then_additively_links_valid_secondary_issn(): void
    {
        $journal = $this->journal();
        $journal->allIdentifiers()->create(['type' => 'electronic_issn', 'value' => '2049-3630']);
        DB::table('journal_accreditations')->insert([
            'item_key' => 'm10-decision-1', 'rank' => 2, 'journal_name' => $journal->name, 'publisher' => 'Penerbit Uji',
            'e_issn_raw' => '20493630', 'e_issn_checksum_valid' => true, 'decision_type' => 'new', 'decision_text' => 'Keputusan uji bersumber',
            'source_page' => 1, 'decree_number' => '355/DST/UJI/2026', 'decree_date' => '2026-07-24', 'period' => '2025-P3',
            'source_pdf_sha256' => str_repeat('a', 64), 'match_status' => 'unmatched', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $linker = app(JournalAccreditationLinker::class);
        $this->assertSame(1, $linker->preview()['linkable']);
        $this->assertDatabaseHas('journal_accreditations', ['item_key' => 'm10-decision-1', 'journal_id' => null]);
        $result = $linker->apply();
        $this->assertSame(1, $result['linked']);
        $this->assertDatabaseHas('journal_accreditations', ['item_key' => 'm10-decision-1', 'journal_id' => $journal->id, 'match_status' => 'matched']);
    }
}
