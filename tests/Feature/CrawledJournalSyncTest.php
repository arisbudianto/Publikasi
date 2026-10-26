<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalAccreditation;
use App\Models\JournalSource;
use App\Models\ScopusSource;
use App\Services\CrawledJournalSync;
use App\Services\JournalPageFetcher;
use App\Services\JournalMetadataSourceSeeder;
use App\Services\ScopusPageMonitor;
use App\Services\SintaPageMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrawledJournalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_publisher_observations_register_for_daily_crawl_without_overwriting_manual_data(): void
    {
        $source = $this->source();
        $journal = Journal::create(['name' => $source->name, 'publisher' => $source->publisher,
            'issn' => $source->e_issn, 'official_url' => $source->official_url,
            'scope_summary' => 'Pendidikan', 'scope_source_url' => $source->official_url,
            'subject' => 'Pendidikan', 'checked_at' => today(), 'status' => 'published']);
        $url = 'https://journal.example.org/fees';
        DB::table('journal_observations')->insert(['journal_id' => $journal->id,
            'kind' => 'fee', 'label' => 'Biaya penerbit', 'value' => 'IDR 750.000',
            'amount_minor' => 75000000, 'currency' => 'IDR', 'verification' => 'publisher_claim',
            'source_url' => $url, 'checked_at' => today(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, app(JournalMetadataSourceSeeder::class)->run()['created']);
        $this->assertSame(0, app(JournalMetadataSourceSeeder::class)->run()['created']);
        $this->assertDatabaseHas('m19_sources', ['journal_id' => $journal->id, 'field' => 'fee',
            'url' => $url, 'enabled' => true]);
        $this->assertTrue(app(CrawledJournalSync::class)->fromRegisteredPage($journal->id, $url,
            'fee', 'The article processing charge is IDR 900,000 after acceptance.'));
        $this->assertDatabaseHas('journal_observations', ['journal_id' => $journal->id,
            'amount_minor' => 75000000, 'retired_at' => null]);
        $this->assertDatabaseHas('journal_observations', ['journal_id' => $journal->id,
            'value' => 'The article processing charge is IDR 900,000 after acceptance.']);
    }

    private function source(bool $decision = true): JournalSource
    {
        $source = JournalSource::create(['source_id' => 891, 'name' => 'Jurnal Pendidikan Uji',
            'publisher' => 'Universitas Uji', 'subject' => 'Pendidikan', 'e_issn' => '2355-6544',
            'education' => true, 'official_url' => 'https://journal.example.org/about',
            'source_url' => 'https://sinta.example.org/journal/891', 'captured_at' => '2025-03-20',
            'raw_identifiers' => [], 'article_titles' => [], 'dataset_sha256' => str_repeat('a', 64)]);
        if ($decision) JournalAccreditation::create(['item_key' => 'decision-891', 'rank' => 2,
            'journal_name' => $source->name, 'e_issn_raw' => '2355-6544',
            'e_issn_checksum_valid' => true, 'decision_text' => 'Accredited',
            'source_page' => 10, 'decree_number' => 'SK TEST', 'decree_date' => '2026-07-24',
            'period' => '2025-3', 'source_pdf_sha256' => str_repeat('b', 64),
            'journal_source_id' => $source->id, 'match_status' => 'matched']);
        return $source;
    }

    public function test_sinta_crawl_publishes_only_a_matched_source_and_keeps_audit(): void
    {
        $source = $this->source();
        config(['sinta_crawl.enabled' => true, 'sinta_crawl.daily_limit' => 1]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><p>The focus and scope of this journal include vocational education and assessment research.</p></body></html>'];
            }
        });
        $result = app(SintaPageMonitor::class)->tick();
        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['candidates']);
        $journal = Journal::firstOrFail();
        $this->assertSame('published', $journal->status);
        $this->assertSame($journal->id, $source->fresh()->journal_id);
        $this->assertDatabaseHas('sinta_page_checks', ['journal_source_id' => $source->id, 'synced' => true]);
        $this->assertDatabaseHas('catalog_audits', ['journal_id' => $journal->id, 'action' => 'created']);
    }

    public function test_unmatched_source_and_archived_journal_are_not_published(): void
    {
        $source = $this->source(false);
        $sync = app(CrawledJournalSync::class);
        $this->assertTrue($sync->fromSinta($source->id, 'https://journal.example.org/scope', 'Aims and scope: vocational education.'));
        $this->assertDatabaseHas('journal_sources', ['id' => $source->id,
            'scope_excerpt' => 'Aims and scope: vocational education.']);
        $this->assertDatabaseCount('journals', 0);
        JournalAccreditation::create(['item_key' => 'decision-892', 'rank' => 2,
            'journal_name' => $source->name, 'e_issn_raw' => '2355-6544',
            'e_issn_checksum_valid' => true, 'decision_text' => 'Accredited',
            'source_page' => 10, 'decree_number' => 'SK TEST', 'decree_date' => '2026-07-24',
            'period' => '2025-3', 'source_pdf_sha256' => str_repeat('b', 64),
            'journal_source_id' => $source->id, 'match_status' => 'matched']);
        $this->assertFalse($sync->fromSinta($source->id, 'https://other.example.org/scope', 'Aims and scope: vocational education.'));
        $journal = Journal::create(['name' => $source->name, 'publisher' => $source->publisher,
            'issn' => $source->e_issn, 'official_url' => $source->official_url,
            'scope_summary' => 'Existing scope', 'scope_source_url' => $source->official_url,
            'subject' => 'Pendidikan', 'checked_at' => today(), 'status' => 'archived']);
        $this->assertFalse($sync->fromSinta($source->id, 'https://journal.example.org/scope', 'Aims and scope: vocational education.'));
        $this->assertSame('archived', $journal->fresh()->status);
        $this->assertDatabaseCount('catalog_audits', 0);
    }

    public function test_scopus_scope_updates_exact_existing_journal_without_changing_index(): void
    {
        $source = $this->source();
        $journal = Journal::create(['name' => $source->name, 'publisher' => $source->publisher,
            'issn' => $source->e_issn, 'official_url' => $source->official_url,
            'scope_summary' => 'Old scope', 'scope_source_url' => $source->official_url,
            'subject' => 'Pendidikan', 'checked_at' => today(), 'status' => 'published']);
        $scopus = ScopusSource::create(['source_record_id' => 77, 'source_title' => $source->name,
            'issn' => '23556544', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true]);
        $target = DB::table('scopus_page_targets')->insertGetId(['scopus_source_id' => $scopus->id,
            'url' => 'https://journal.example.org/about', 'field' => 'scope', 'enabled' => true,
            'created_at' => now(), 'updated_at' => now()]);
        config(['scopus_crawl.enabled' => true]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><p>The focus and scope of this journal include vocational education and assessment research.</p></body></html>'];
            }
        });
        $this->assertSame(1, app(ScopusPageMonitor::class)->tick()['synced']);
        $this->assertStringContainsString('vocational education', $journal->fresh()->scope_summary);
        $this->assertDatabaseHas('scopus_page_checks', ['target_id' => $target, 'synced' => true]);
        $this->assertDatabaseHas('scopus_sources', ['id' => $scopus->id,
            'scope_source_url' => 'https://journal.example.org/about']);
        $this->assertSame('Active', $scopus->fresh()->status);
    }
}
