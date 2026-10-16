<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalAccreditation;
use App\Models\JournalSource;
use App\Services\SintaAccreditationDataset;
use App\Services\SintaDataset;
use Database\Seeders\PilotJournalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SintaAccreditationTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_decisions_import_additively_and_link_only_valid_exact_issns(): void
    {
        $source = JournalSource::create([
            'source_id' => 990001,
            'name' => 'Geoplanning: Journal of Geomatics and Planning',
            'publisher' => 'Universitas Diponegoro',
            'education' => false,
            'p_issn' => null,
            'e_issn' => '2355-6544',
            'claimed_rank' => 'Sinta 4',
            'source_url' => 'https://sinta.kemdiktisaintek.go.id/journals/profile/990001',
            'captured_at' => '2025-03-20',
            'raw_identifiers' => [],
            'article_titles' => [],
            'dataset_sha256' => str_repeat('a', 64),
        ]);
        $journal = Journal::create([
            'name' => 'Wacana, Journal of the Humanities of Indonesia',
            'publisher' => 'Universitas Indonesia',
            'issn' => '2407-6899',
            'official_url' => 'https://example.org',
            'scope_summary' => 'Scope yang telah ditinjau secara editorial.',
            'subject' => 'Humaniora',
            'scope_source_url' => 'https://example.org/scope',
            'checked_at' => today(),
            'status' => 'published',
        ]);

        $dataset = app(SintaAccreditationDataset::class);
        $preview = $dataset->preview();
        $this->assertSame(2697, count($preview['rows']));
        $this->assertSame(['1' => 28, '2' => 192, '3' => 658, '4' => 1130, '5' => 610, '6' => 79], $preview['manifest']['rank_counts']);
        $this->assertSame(2675, $preview['manifest']['eissn_checksum_valid']);
        $this->assertSame(18, $preview['manifest']['eissn_checksum_invalid']);

        $result = $dataset->import();
        $this->assertSame(2697, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['linked_sources']);
        $this->assertSame(1, $result['linked_journals']);
        $this->assertDatabaseCount('journal_sources', 1);
        $this->assertDatabaseCount('journals', 1);
        $this->assertSame('Sinta 4', $source->fresh()->claimed_rank);
        $this->assertSame('published', $journal->fresh()->status);

        $matchedSourceRecord = JournalAccreditation::where('e_issn_raw', '23556544')->firstOrFail();
        $this->assertSame($source->id, $matchedSourceRecord->journal_source_id);
        $this->assertSame(1, $matchedSourceRecord->rank);
        $this->assertSame('Akreditasi Baru', $matchedSourceRecord->decision_type);
        $this->assertSame(2023, $matchedSourceRecord->valid_from_year);
        $this->assertSame(2028, $matchedSourceRecord->valid_to_year);

        $journal->observations()->create([
            'kind' => 'fee', 'label' => 'APC akses terbuka', 'value' => 'Gratis',
            'amount_minor' => 0, 'currency' => 'IDR', 'fee_route' => 'apc_oa',
            'verification' => 'verified', 'source_url' => 'https://example.org/fees', 'checked_at' => today(),
        ]);
        $journal->observations()->create([
            'kind' => 'time', 'label' => 'Estimasi review', 'value' => '6 minggu',
            'verification' => 'publisher_claim', 'source_url' => 'https://example.org/review', 'checked_at' => today(),
        ]);
        $journal->observations()->create([
            'kind' => 'time', 'label' => 'Estimasi lama', 'value' => '99 minggu',
            'verification' => 'verified', 'source_url' => 'https://example.org/old', 'checked_at' => today()->subYear(),
            'effective_to' => today()->subDay(),
        ]);

        $this->get('/akreditasi-sinta?q=24076899')
            ->assertOk()
            ->assertSee('Scope yang telah ditinjau secara editorial.')
            ->assertSee('Lihat sumber scope')
            ->assertSee('IDR 0')
            ->assertSee('6 minggu')
            ->assertSee('Klaim penerbit, belum diverifikasi independen')
            ->assertSee('Peluang diterima:')
            ->assertDontSee('99 minggu')
            ->assertDontSee('Keputusan resmi');

        $invalid = JournalAccreditation::where('e_issn_raw', '2354911')->firstOrFail();
        $this->assertFalse($invalid->e_issn_checksum_valid);
        $this->assertNull($invalid->journal_source_id);
        $this->assertSame('invalid_issn', $invalid->match_status);
        $missing = JournalAccreditation::where('item_key', '355-2026:2:182')->firstOrFail();
        $this->assertNull($missing->e_issn_raw);
        $this->assertSame('missing_issn', $missing->match_status);

        $replay = $dataset->import();
        $this->assertSame(0, $replay['created']);
        $this->assertSame(2697, $replay['skipped']);
        $this->assertDatabaseCount('journal_accreditations', 2697);
    }

    public function test_public_directory_search_and_existing_source_detail_show_linked_decision(): void
    {
        $source = JournalSource::create([
            'source_id' => 990002,
            'name' => 'Geoplanning: Journal of Geomatics and Planning',
            'publisher' => 'Universitas Diponegoro',
            'education' => false,
            'p_issn' => null,
            'e_issn' => '2355-6544',
            'claimed_rank' => 'Sinta 4',
            'source_url' => 'https://sinta.kemdiktisaintek.go.id/journals/profile/990002',
            'captured_at' => '2025-03-20',
            'raw_identifiers' => [],
            'article_titles' => [],
            'dataset_sha256' => str_repeat('a', 64),
        ]);
        app(SintaAccreditationDataset::class)->import();

        $this->get('/akreditasi-sinta?q=23556544')
            ->assertOk()->assertSee('Hasil akreditasi SINTA')->assertSee('Geoplanning: Journal of Geomatics and Planning')->assertSee('SINTA 1')
            ->assertSee('Scope belum tersedia dalam katalog terkurasi.')
            ->assertDontSee('Belum ditautkan melalui ISSN');
        $this->get('/akreditasi-sinta')
            ->assertOk()
            ->assertSee('catalog.css?v=', false)
            ->assertSee('Kategori jurnal SINTA 1–4')
            ->assertSee('SINTA 1, 28 keputusan', false)
            ->assertSee('SINTA 2, 192 keputusan', false)
            ->assertSee('SINTA 3, 658 keputusan', false)
            ->assertSee('Filter peringkat SINTA 4, 1130 keputusan', false)
            ->assertSee('Semua peringkat SINTA 1–4')
            ->assertDontSee('SINTA 5</option>', false)
            ->assertDontSee('SINTA 6</option>', false);
        $this->getJson('/akreditasi-sinta?rank=6')
            ->assertUnprocessable();
        $this->get('/akreditasi-sinta?rank=4')
            ->assertOk()->assertSee('SINTA 4')->assertDontSee('class="accreditation-rank">SINTA 6', false);
        $this->get('/sumber-jurnal/'.$source->id)
            ->assertOk()->assertSee('Keputusan akreditasi yang cocok')->assertSee('Peringkat 1');
    }

    public function test_full_period_3_decisions_link_to_the_existing_999_record_snapshot_without_overwriting_it(): void
    {
        app(PilotJournalSeeder::class)->run();
        $this->assertDatabaseCount('journals', 5);
        $curatedIssns = Journal::orderBy('id')->pluck('issn')->all();

        $sinta = app(SintaDataset::class)->import();
        $this->assertSame(999, $sinta['created']);
        $this->assertSame(999, JournalSource::count());
        $this->assertDatabaseCount('journals', 5);

        $result = app(SintaAccreditationDataset::class)->import();

        $this->assertSame(2697, $result['created']);
        $this->assertSame(83, $result['linked_sources']);
        $this->assertSame(0, $result['linked_journals']);
        $this->assertDatabaseCount('journal_sources', 999);
        $this->assertDatabaseCount('journals', 5);
        $this->assertSame($curatedIssns, Journal::orderBy('id')->pluck('issn')->all());

        $geoplanning = JournalSource::where('source_id', 933)->firstOrFail();
        $this->assertSame('Sinta 1', $geoplanning->claimed_rank);
        $this->assertDatabaseHas('journal_accreditations', [
            'item_key' => '355-2026:1:1',
            'e_issn_raw' => '23556544',
            'journal_source_id' => $geoplanning->id,
            'rank' => 1,
            'match_status' => 'matched',
        ]);
    }
}
