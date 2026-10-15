<?php

namespace Tests\Feature;

use App\Models\ScopusSource;
use App\Models\User;
use App\Services\JournalPageFetcher;
use App\Services\ScopusPageMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScopusPageMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function source(int $id): ScopusSource
    {
        return ScopusSource::create(['source_record_id' => $id, 'source_title' => 'Test Journal '.$id,
            'issn' => '12345678', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true]);
    }

    public function test_admin_registers_official_page_and_reviews_quote_without_changing_index(): void
    {
        $source = $this->source(1);
        $route = route('admin.scopus-crawl.index');
        $this->get($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($route)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($route.'?q=Test')->assertOk()->assertSee('Test Journal 1');
        $this->post(route('admin.scopus-crawl.store'), ['scopus_source_id' => $source->id,
            'url' => 'https://journal.example.org/about', 'field' => 'scope'])->assertRedirect();
        $target = DB::table('scopus_page_targets')->first();
        $this->assertFalse((bool) $target->enabled);
        $this->post(route('admin.scopus-crawl.store'), ['scopus_source_id' => $source->id,
            'url' => 'http://localhost/about', 'field' => 'scope'])->assertSessionHasErrors('url');
        $this->post(route('admin.scopus-crawl.toggle', $target->id), ['enabled' => 1])->assertRedirect();
        config(['scopus_crawl.enabled' => true]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><p>The focus and scope of this journal include vocational education and assessment research.</p></body></html>'];
            }
        });
        $this->assertSame(1, app(ScopusPageMonitor::class)->tick()['checked']);
        $this->assertSame(0, app(ScopusPageMonitor::class)->tick()['checked']);
        $this->assertDatabaseCount('scopus_page_candidates', 1);
        $candidate = DB::table('scopus_page_candidates')->value('id');
        $this->get($route)->assertOk()->assertSee('vocational education');
        $this->post(route('admin.scopus-crawl.review', $candidate), ['decision' => 'reviewed',
            'note' => 'Verified on publisher site'])->assertRedirect();
        $this->assertDatabaseHas('scopus_page_candidates', ['id' => $candidate, 'status' => 'reviewed']);
        $this->assertSame('Active', $source->fresh()->status);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_shared_daily_quota_limits_scopus_checks(): void
    {
        config(['scopus_crawl.enabled' => true, 'scopus_crawl.daily_limit' => 10]);
        for ($i = 1; $i <= 10; $i++) {
            $source = $this->source($i);
            DB::table('scopus_page_targets')->insert(['scopus_source_id' => $source->id,
                'url' => 'https://journal.example.org/about', 'field' => 'scope', 'enabled' => true,
                'created_at' => now(), 'updated_at' => now()]);
        }
        // Existing SINTA checks occupy 15 of the 20 shared slots.
        $sinta = \App\Models\JournalSource::create(['source_id' => 100, 'name' => 'Test SINTA',
            'education' => true, 'source_url' => 'https://sinta.example.org/test', 'captured_at' => '2025-03-20',
            'raw_identifiers' => [], 'article_titles' => [], 'dataset_sha256' => str_repeat('a', 64)]);
        for ($i = 0; $i < 15; $i++) {
            DB::table('sinta_page_checks')->insert(['journal_source_id' => $sinta->id, 'url' => 'https://journal.example.org/about',
                'status' => 'unchanged', 'created_at' => now()]);
        }
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body></body></html>'];
            }
        });
        $this->assertSame(5, app(ScopusPageMonitor::class)->tick()['checked']);
        $this->assertDatabaseCount('scopus_page_checks', 5);
    }
}
