<?php

namespace Tests\Feature;

use App\Models\JournalAccreditation;
use App\Models\JournalSource;
use App\Models\ScopusSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexedCandidateMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_use_linked_sinta_and_active_non_discontinued_scopus_records(): void
    {
        $sinta = JournalSource::create([
            'source_id' => 997, 'name' => 'Jurnal Pembelajaran Vokasi', 'subject' => 'Pembelajaran vokasi',
            'e_issn' => '1234-5679', 'source_url' => 'https://example.org/sinta', 'captured_at' => '2025-03-20',
            'education' => true, 'raw_identifiers' => [], 'article_titles' => ['Pelatihan vokasi digital'],
            'dataset_sha256' => str_repeat('a', 64),
        ]);
        JournalAccreditation::create([
            'item_key' => 'test:1', 'rank' => 2, 'journal_name' => $sinta->name,
            'e_issn_raw' => '12345679', 'e_issn_checksum_valid' => true,
            'decision_text' => 'Akreditasi', 'source_page' => 1, 'decree_number' => 'Test',
            'decree_date' => '2026-07-24', 'period' => '2025-3', 'valid_to_year' => 2028, 'source_pdf_sha256' => str_repeat('b', 64),
            'journal_source_id' => $sinta->id, 'match_status' => 'matched',
        ]);
        foreach ([
            [1, 'Journal of Vocational Learning', 'Active', false, '22222222'],
            [2, 'Discontinued Vocational Journal', 'Active', true, '33333333'],
            [3, 'Inactive Vocational Journal', 'Inactive', false, '44444444'],
        ] as [$id, $name, $status, $discontinued, $issn]) {
            ScopusSource::create(['source_record_id' => $id, 'source_title' => $name,
                'eissn' => $issn, 'status' => $status, 'discontinued' => $discontinued,
                'source_type' => 'Journal', 'snapshot_month' => '2026-09', 'is_current' => true]);
        }
        for ($i = 4; $i <= 28; $i++) {
            ScopusSource::create(['source_record_id' => $i, 'source_title' => 'Vocational Learning Studies '.$i,
                'eissn' => str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'status' => 'Active',
                'discontinued' => false, 'source_type' => 'Journal', 'snapshot_month' => '2026-09', 'is_current' => true]);
        }
        $payload = ['title' => 'Vocational learning curriculum', 'abstract' => str_repeat('Vocational learning and digital training. ', 4)];
        $this->post('/cocokkan', $payload)->assertOk()->assertSeeInOrder(['Journal of Vocational Learning', 'Jurnal Pembelajaran Vokasi'])
            ->assertSee('11 kandidat')
            ->assertSee('Jurnal Pembelajaran Vokasi')
            ->assertSee('Journal of Vocational Learning')->assertSee('S2')
            ->assertDontSee('Discontinued Vocational Journal')->assertDontSee('Inactive Vocational Journal');
        $this->post('/cocokkan', $payload + ['index' => 'scopus'])->assertOk()
            ->assertSee('Journal of Vocational Learning')->assertDontSee('Jurnal Pembelajaran Vokasi');
        $this->post('/cocokkan', $payload + ['index' => 'sinta'])->assertOk()
            ->assertSee('Jurnal Pembelajaran Vokasi')->assertDontSee('Journal of Vocational Learning');
    }
}
