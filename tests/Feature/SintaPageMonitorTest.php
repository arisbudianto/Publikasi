<?php

namespace Tests\Feature;

use App\Models\JournalSource;
use App\Models\User;
use App\Services\JournalPageFetcher;
use App\Services\SintaPageMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SintaPageMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function source(int $id, string $url = 'https://journal.example.org/about'): JournalSource
    {
        return JournalSource::create(['source_id' => $id, 'name' => 'Jurnal Uji '.$id, 'education' => true,
            'official_url' => $url, 'source_url' => 'https://sinta.kemdikbud.go.id/journals/profile/'.$id,
            'captured_at' => '2025-03-20', 'raw_identifiers' => [], 'article_titles' => [],
            'dataset_sha256' => str_repeat('a', 64)]);
    }

    public function test_daily_checks_are_bounded_rotated_and_do_not_change_index_data(): void
    {
        for ($i = 1; $i <= 22; $i++) {
            $this->source($i);
        }
        config(['sinta_crawl.enabled' => true, 'sinta_crawl.daily_limit' => 15]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><p>The focus and scope of this journal include vocational education and assessment research.</p></body></html>', 'content_type' => 'text/html'];
            }
        });
        $first = app(SintaPageMonitor::class)->tick();
        $this->assertSame(15, $first['checked']);
        $this->assertSame(15, $first['candidates']);
        $this->assertSame(0, app(SintaPageMonitor::class)->tick()['checked']);
        $this->assertDatabaseCount('sinta_page_checks', 15);
        $this->assertDatabaseCount('journal_sources', 22);
        $this->assertDatabaseCount('journals', 0);
        $this->travel(1)->days();
        $this->assertSame(7, app(SintaPageMonitor::class)->tick()['checked']);
        $this->assertDatabaseCount('sinta_page_checks', 22);
    }

    public function test_robots_error_is_recorded_without_candidate_and_admin_can_review_evidence(): void
    {
        $source = $this->source(1);
        $this->assertSame('disabled', app(SintaPageMonitor::class)->tick()['status']);
        config(['sinta_crawl.enabled' => true]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                throw new RuntimeException('robots_unreachable');
            }
        });
        $this->assertSame(1, app(SintaPageMonitor::class)->tick()['errors']);
        $this->assertDatabaseHas('sinta_page_checks', ['journal_source_id' => $source->id,
            'status' => 'error', 'error_code' => 'robots_unreachable']);
        $this->assertDatabaseCount('sinta_page_candidates', 0);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.agent.index'))->assertOk()->assertSee('robots_unreachable');
        $check = DB::table('sinta_page_checks')->value('id');
        $candidate = DB::table('sinta_page_candidates')->insertGetId(['journal_source_id' => $source->id,
            'check_id' => $check, 'quote' => 'This journal covers vocational research.', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('admin.agent.index'))->assertSee('This journal covers vocational research.');
        $this->post(route('admin.agent.sinta.review', $candidate), ['decision' => 'reviewed'])->assertRedirect();
        $this->assertDatabaseHas('sinta_page_candidates', ['id' => $candidate, 'status' => 'reviewed']);
    }

    public function test_historical_http_url_is_probed_only_over_https_without_changing_the_import(): void
    {
        $source = $this->source(77, 'http://journal.example.org/about');
        config(['sinta_crawl.enabled' => true, 'sinta_crawl.daily_limit' => 1]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                if ($url !== 'https://journal.example.org/about') {
                    throw new RuntimeException('unsafe_url');
                }
                return ['status' => 200, 'body' => '<html><body></body></html>', 'content_type' => 'text/html'];
            }
        });
        $this->assertSame(1, app(SintaPageMonitor::class)->tick()['changed']);
        $this->assertDatabaseHas('sinta_page_checks', ['journal_source_id' => $source->id,
            'url' => 'https://journal.example.org/about', 'status' => 'changed']);
        $this->assertSame('http://journal.example.org/about', $source->fresh()->official_url);
    }

    public function test_https_source_is_checked_before_historical_http_source(): void
    {
        $old = $this->source(1, 'http://old.example.org/about');
        $secure = $this->source(2, 'https://secure.example.org/about');
        config(['sinta_crawl.enabled' => true, 'sinta_crawl.daily_limit' => 1]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body></body></html>', 'content_type' => 'text/html'];
            }
        });
        $this->assertSame(1, app(SintaPageMonitor::class)->tick()['checked']);
        $this->assertDatabaseHas('sinta_page_checks', ['journal_source_id' => $secure->id]);
        $this->assertDatabaseMissing('sinta_page_checks', ['journal_source_id' => $old->id]);
    }

    public function test_same_host_scope_link_produces_reviewable_evidence(): void
    {
        $source = $this->source(1);
        config(['sinta_crawl.enabled' => true, 'sinta_crawl.daily_limit' => 1]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                $body = str_ends_with($url, '/scope')
                    ? '<html><body><p>The focus and scope of this journal include vocational education and assessment research.</p></body></html>'
                    : '<html><body><nav><a href="https://journal.example.org/scope">Focus and Scope</a><a href="https://evil.test/scope">Focus and Scope</a></nav></body></html>';
                return ['status' => 200, 'body' => $body, 'content_type' => 'text/html'];
            }
        });
        $result = app(SintaPageMonitor::class)->tick();
        $this->assertSame(1, $result['candidates']);
        $this->assertDatabaseHas('sinta_page_checks', ['journal_source_id' => $source->id,
            'url' => 'https://journal.example.org/scope']);
        $this->assertDatabaseCount('sinta_page_candidates', 1);
    }

    public function test_existing_m19_checks_reduce_the_shared_daily_budget(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->source($i);
        }
        config(['sinta_crawl.enabled' => true, 'sinta_crawl.daily_limit' => 20]);
        DB::table('sinta_page_checks')->insert(['journal_source_id' => JournalSource::first()->id,
            'url' => 'https://journal.example.org/about', 'status' => 'error', 'created_at' => now()]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body></body></html>', 'content_type' => 'text/html'];
            }
        });
        $this->assertSame(19, app(SintaPageMonitor::class)->tick()['checked']);
        $this->assertDatabaseCount('sinta_page_checks', 20);
    }
}
