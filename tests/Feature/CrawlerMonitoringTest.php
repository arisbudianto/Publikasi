<?php

namespace Tests\Feature;

use App\Models\JournalSource;
use App\Models\ScopusSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrawlerMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_both_sources_and_can_filter_history_without_changing_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sinta = JournalSource::create(['source_id' => 31, 'name' => 'SINTA Education Journal',
            'education' => true, 'official_url' => 'https://sinta.example.org/journal',
            'source_url' => 'https://sinta.example.org/profile/31', 'captured_at' => '2025-03-20',
            'raw_identifiers' => [], 'article_titles' => [], 'dataset_sha256' => str_repeat('a', 64)]);
        $scopus = ScopusSource::create(['source_record_id' => 41, 'source_title' => 'Scopus Engineering Journal',
            'issn' => '12345678', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true]);
        $target = DB::table('scopus_page_targets')->insertGetId(['scopus_source_id' => $scopus->id,
            'url' => 'https://scopus.example.org/journal', 'field' => 'scope', 'enabled' => true,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('sinta_page_checks')->insert(['journal_source_id' => $sinta->id,
            'url' => 'https://sinta.example.org/journal', 'status' => 'changed', 'http_status' => 200,
            'created_at' => now()]);
        DB::table('scopus_page_checks')->insert(['target_id' => $target,
            'status' => 'error', 'error_code' => 'robots_unreachable', 'created_at' => now()]);
        $url = route('admin.scopus-crawl.index');
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Monitoring crawler SINTA dan Scopus')
            ->assertSee('SINTA Education Journal')->assertSee('Scopus Engineering Journal')
            ->assertSee('robots_unreachable')->assertSee('1</strong> berubah', false)
            ->assertSee('1</strong> gagal', false);
        $this->get($url.'?source=scopus&status=error&period=today')->assertOk()
            ->assertSee('Scopus Engineering Journal')->assertDontSee('SINTA Education Journal');
        $this->assertDatabaseCount('sinta_page_checks', 1);
        $this->assertDatabaseCount('scopus_page_checks', 1);
    }
}
