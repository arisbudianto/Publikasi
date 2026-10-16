<?php

namespace Tests\Feature;

use App\Models\JournalSource;
use App\Models\ScopusSource;
use App\Services\JournalPageFetcher;
use App\Services\ScopusTargetVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScopusTargetVerifierTest extends TestCase
{
    use RefreshDatabase;

    private function target(string $url = 'https://journal.example.org/about'): int
    {
        $scopus = ScopusSource::create(['source_record_id' => 71, 'source_title' => 'Journal of Vocational Learning',
            'issn' => '12345678', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true]);
        JournalSource::create(['source_id' => 71, 'name' => 'Journal of Vocational Learning',
            'education' => true, 'p_issn' => '1234-5678', 'official_url' => $url,
            'source_url' => 'https://sinta.example.org/71', 'captured_at' => '2025-03-20',
            'raw_identifiers' => [], 'article_titles' => [], 'dataset_sha256' => str_repeat('a', 64)]);
        return DB::table('scopus_page_targets')->insertGetId(['scopus_source_id' => $scopus->id,
            'url' => $url, 'field' => 'scope', 'enabled' => false, 'auto_verify' => true,
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_matching_title_and_issn_activate_target_once_without_changing_scopus_record(): void
    {
        config(['scopus_crawl.enabled' => true]);
        $id = $this->target();
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<title>Journal of Vocational Learning</title><p>ISSN 1234-5678</p>'];
            }
        });
        $this->assertSame(1, app(ScopusTargetVerifier::class)->tick()['activated']);
        $this->assertDatabaseHas('scopus_page_targets', ['id' => $id, 'enabled' => true, 'auto_verify' => false]);
        $this->assertSame(0, app(ScopusTargetVerifier::class)->tick()['checked']);
        $this->assertDatabaseCount('scopus_page_checks', 0);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_unrelated_page_is_rejected_and_retried_later(): void
    {
        config(['scopus_crawl.enabled' => true]);
        $id = $this->target();
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<title>Other Journal</title><p>ISSN 1234-5678</p>'];
            }
        });
        $this->assertSame(1, app(ScopusTargetVerifier::class)->tick()['rejected']);
        $this->assertDatabaseHas('scopus_page_targets', ['id' => $id, 'enabled' => false,
            'verification_error' => 'page_identity_mismatch']);
        $this->assertSame(0, app(ScopusTargetVerifier::class)->tick()['checked']);
    }

    public function test_manual_pause_stays_paused_and_shared_quota_is_respected(): void
    {
        config(['scopus_crawl.enabled' => true]);
        $id = $this->target();
        DB::table('scopus_page_targets')->where('id', $id)->update(['auto_verify' => false]);
        $this->assertSame(0, app(ScopusTargetVerifier::class)->tick()['checked']);
        DB::table('scopus_page_targets')->where('id', $id)->update(['auto_verify' => true]);
        $sinta = JournalSource::first();
        for ($i = 0; $i < 20; $i++) {
            DB::table('sinta_page_checks')->insert(['journal_source_id' => $sinta->id,
                'url' => $sinta->official_url, 'status' => 'unchanged', 'created_at' => now()]);
        }
        $this->assertSame(0, app(ScopusTargetVerifier::class)->tick()['checked']);
    }
}
