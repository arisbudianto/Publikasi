<?php

namespace Tests\Feature;

use App\Models\ScopusSource;
use App\Services\ScopusSourceDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopusSourceDatasetTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_search_distinguishes_snapshot_status_and_discontinued_flag(): void
    {
        ScopusSource::create([
            'source_record_id' => 123, 'source_title' => 'Journal of Testing', 'issn' => '12345679',
            'status' => 'Inactive', 'discontinued' => false, 'source_type' => 'Journal',
            'coverage' => '2017-2024', 'snapshot_month' => '2026-09', 'is_current' => true,
        ]);
        $this->get('/sumber-scopus?q=1234-5679')->assertOk()->assertSee('Journal of Testing')->assertSee('Inactive')->assertDontSee('Ditandai discontinued');
        $this->post('/asisten', ['question' => 'Apakah jurnal ISSN 1234-5679 terindeks Scopus?'])
            ->assertOk()->assertSee('2017-2024')->assertSee('Periksa status terbaru');
    }

    public function test_import_rejects_invalid_and_old_snapshot_without_mutating_catalog(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scopus-');
        $handle = fopen($path, 'wb');
        fputcsv($handle, ScopusSourceDataset::HEADERS);
        fputcsv($handle, ['123', 'Valid Journal', '12345679', '', 'Active', '2020-2025', '0', 'Journal', 'Publisher', '1400']);
        fputcsv($handle, ['123', 'Duplicate ID', '', '', 'Active', '', '0', 'Journal', '', '']);
        fclose($handle);
        try {
            $preview = app(ScopusSourceDataset::class)->preview($path, '2026-09');
            $this->assertCount(1, $preview['rows']);
            $this->assertSame(1, $preview['error_count']);
            $this->assertDatabaseCount('scopus_sources', 0);
            ScopusSource::create(['source_record_id' => 4, 'source_title' => 'Existing', 'status' => 'Active', 'source_type' => 'Journal', 'snapshot_month' => '2026-10', 'is_current' => true]);
            $this->expectException(\Illuminate\Validation\ValidationException::class);
            app(ScopusSourceDataset::class)->preview($path, '2026-09');
        } finally {
            unlink($path);
        }
    }

    public function test_new_snapshot_keeps_scope_only_for_the_same_source_and_issn(): void
    {
        ScopusSource::create(['source_record_id' => 1, 'source_title' => 'Known Journal',
            'issn' => '12345679', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true,
            'scope_excerpt' => 'The focus and scope include vocational education.',
            'scope_source_url' => 'https://journal.example.org/scope', 'scope_checked_at' => today()]);
        ScopusSource::create(['source_record_id' => 2, 'source_title' => 'Changed Journal',
            'issn' => '12345679', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true,
            'scope_excerpt' => 'The focus and scope include vocational education.',
            'scope_source_url' => 'https://changed.example.org/scope', 'scope_checked_at' => today()]);
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = ['source_record_id' => $i, 'source_title' => 'Journal '.$i,
                'issn' => $i === 2 ? '23456787' : '12345679', 'eissn' => null,
                'status' => 'Active', 'coverage' => null, 'discontinued' => '0',
                'source_type' => 'Journal', 'publisher' => null, 'asjc_codes' => null];
        }
        app(ScopusSourceDataset::class)->import($rows, '2026-10');
        $this->assertNotNull(ScopusSource::where('source_record_id', 1)->where('is_current', true)->value('scope_excerpt'));
        $this->assertNull(ScopusSource::where('source_record_id', 2)->where('is_current', true)->value('scope_excerpt'));
    }
}
