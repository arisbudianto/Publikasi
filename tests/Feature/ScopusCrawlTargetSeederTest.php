<?php

namespace Tests\Feature;

use App\Models\JournalSource;
use App\Models\ScopusSource;
use App\Services\ScopusCrawlTargetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScopusCrawlTargetSeederTest extends TestCase
{
    use RefreshDatabase;

    private function scopus(int $id, string $issn): ScopusSource
    {
        return ScopusSource::create(['source_record_id' => $id, 'source_title' => 'Journal '.$id,
            'issn' => $issn, 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true]);
    }

    private function sinta(int $id, string $issn, string $url): JournalSource
    {
        return JournalSource::create(['source_id' => $id, 'name' => 'Journal '.$id,
            'p_issn' => $issn, 'official_url' => $url,
            'source_url' => 'https://sinta.example.org/'.$id, 'captured_at' => '2025-03-20',
            'raw_identifiers' => [], 'article_titles' => [], 'dataset_sha256' => str_repeat('a', 64)]);
    }

    public function test_exact_issn_match_seeds_paused_targets_and_is_idempotent(): void
    {
        $source = $this->scopus(1, '12345678');
        $this->sinta(1, '1234-5678', 'https://journal.example.org/about');
        $this->assertSame(['eligible' => 1, 'created' => 0, 'mode' => 'dry-run'],
            app(ScopusCrawlTargetSeeder::class)->run());
        $this->assertDatabaseCount('scopus_page_targets', 0);
        $this->assertSame(1, app(ScopusCrawlTargetSeeder::class)->run(50, true)['created']);
        $this->assertDatabaseHas('scopus_page_targets', ['scopus_source_id' => $source->id,
            'url' => 'https://journal.example.org/about', 'field' => 'scope', 'enabled' => false]);
        $this->assertSame(0, app(ScopusCrawlTargetSeeder::class)->run(50, true)['created']);
    }

    public function test_ambiguous_and_insecure_matches_are_skipped(): void
    {
        $this->scopus(1, '12345678');
        $this->scopus(2, '12345678');
        $this->sinta(1, '12345678', 'https://journal.example.org/about');
        $this->sinta(2, '12345678', 'http://journal.example.org/about');
        $this->assertSame(0, app(ScopusCrawlTargetSeeder::class)->run(50, true)['created']);
        $this->assertSame(0, DB::table('scopus_page_targets')->count());
    }
}
