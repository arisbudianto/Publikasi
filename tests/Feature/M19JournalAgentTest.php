<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\User;
use App\Services\JournalEvidenceExtractor;
use App\Services\JournalAiExtractor;
use App\Services\JournalMonitoringAgent;
use App\Services\JournalPageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class M19JournalAgentTest extends TestCase
{
    use RefreshDatabase;

    private function journal(): Journal
    {
        return Journal::create(['name' => 'Jurnal Vokasi Uji', 'publisher' => 'Penerbit Uji', 'issn' => '2088-2866',
            'subject' => 'Vokasi', 'scope_summary' => 'Pendidikan vokasi',
            'scope_source_url' => 'https://journal.example.org/about', 'official_url' => 'https://journal.example.org',
            'checked_at' => today(), 'status' => 'published']);
    }

    public function test_seed_scope_previews_and_registers_only_curated_same_host_urls(): void
    {
        $journal = $this->journal();
        $this->artisan('m19:seed-scope', ['--limit' => 20])->assertSuccessful();
        $this->assertDatabaseCount('m19_sources', 0);
        $this->artisan('m19:seed-scope', ['--limit' => 20, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('m19_sources', ['journal_id' => $journal->id, 'field' => 'scope', 'enabled' => 1]);
        $this->artisan('m19:seed-scope', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('m19_sources', 1);
        $other = $journal->replicate();
        $other->issn = '2099-2866';
        $other->scope_source_url = 'https://other.example.org/about';
        $other->save();
        $this->artisan('m19:seed-scope', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('m19_sources', 1);
    }

    public function test_admin_registers_exact_official_host_and_cannot_publish_by_reviewing_agent_quote(): void
    {
        $journal = $this->journal();
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $data = ['journal_id' => $journal->id, 'url' => 'https://journal.example.org/about/submissions', 'field' => 'fee'];
        $this->get(route('admin.agent.index'))->assertRedirect();
        $this->actingAs($user)->get(route('admin.agent.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.agent.store'), array_merge($data, ['url' => 'https://journal.example.org.evil.test/about']))
            ->assertSessionHasErrors('url');
        $this->post(route('admin.agent.store'), $data)->assertRedirect();
        $this->assertDatabaseHas('m19_sources', ['journal_id' => $journal->id, 'enabled' => 0]);
        $source = DB::table('m19_sources')->first();
        $this->post(route('admin.agent.toggle', $source->id), ['enabled' => 1])->assertRedirect();
        $run = DB::table('m19_runs')->insertGetId(['source_id' => $source->id, 'status' => 'changed', 'created_at' => now()]);
        $candidate = DB::table('m19_candidates')->insertGetId(['source_id' => $source->id, 'run_id' => $run,
            'field' => 'fee', 'url' => $data['url'], 'quote' => 'Publication fee is 500 USD.',
            'content_hash' => str_repeat('a', 64), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('admin.agent.index'))->assertOk()->assertSee('Publication fee is 500 USD.');
        $this->post(route('admin.agent.review', $candidate), ['decision' => 'reviewed', 'note' => 'Checked against the official site.'])
            ->assertRedirect();
        $this->assertDatabaseHas('m19_candidates', ['id' => $candidate, 'status' => 'reviewed']);
        $this->assertDatabaseCount('journal_observations', 0);
    }

    public function test_scheduled_agent_creates_evidence_only_when_changed_and_is_disabled_by_default(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 03:34:00', 'UTC'));
        $journal = $this->journal();
        $source = DB::table('m19_sources')->insertGetId(['journal_id' => $journal->id,
            'url' => 'https://journal.example.org/about', 'field' => 'fee', 'enabled' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('disabled', app(JournalMonitoringAgent::class)->tick()['status']);
        config(['m19.enabled' => true]);
        $fake = new class extends JournalPageFetcher
        {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><main><p>Publication fee is 500 USD per article.</p></main></body></html>', 'content_type' => 'text/html'];
            }
        };
        $this->app->instance(JournalPageFetcher::class, $fake);
        $this->assertSame(1, app(JournalMonitoringAgent::class)->tick()['changed']);
        $this->assertDatabaseCount('m19_candidates', 1);
        $this->assertDatabaseCount('journal_observations', 0);
        $this->assertSame('2026-10-16 03:00:00',
            DB::table('m19_sources')->where('id', $source)->value('next_check_at'));
        $this->assertSame(0, app(JournalMonitoringAgent::class)->tick()['checked']);
        DB::table('m19_sources')->where('id', $source)->update(['next_check_at' => now()->subMinute()]);
        $this->assertSame(0, app(JournalMonitoringAgent::class)->tick()['changed']);
        $this->assertDatabaseCount('m19_candidates', 1);
    }

    public function test_daily_quota_counts_journals_and_avoids_a_second_source_for_the_same_journal(): void
    {
        config(['m19.enabled' => true, 'm19.batch_limit' => 10]);
        for ($i = 0; $i < 12; $i++) {
            $journal = Journal::create(['name' => 'Jurnal Uji '.$i, 'publisher' => 'Penerbit Uji',
                'issn' => sprintf('2088-%04d', $i), 'subject' => 'Vokasi', 'scope_summary' => 'Pendidikan vokasi',
                'scope_source_url' => 'https://journal.example.org/about', 'official_url' => 'https://journal.example.org',
                'checked_at' => today(), 'status' => 'published']);
            foreach ($i === 0 ? ['fee', 'scope'] : ['fee'] as $field) {
                DB::table('m19_sources')->insert(['journal_id' => $journal->id, 'url' => 'https://journal.example.org/about',
                    'field' => $field, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><p>Publication fee is 500 USD per article.</p></body></html>', 'content_type' => 'text/html'];
            }
        });
        $this->assertSame(10, app(JournalMonitoringAgent::class)->tick()['checked']);
        $this->assertSame(0, app(JournalMonitoringAgent::class)->tick()['checked']);
        $this->assertSame(10, DB::table('m19_runs')->count());
        $this->assertSame(10, DB::table('m19_sources')->whereNotNull('last_checked_at')->count());
    }

    public function test_url_and_robots_rules_reject_unsafe_targets(): void
    {
        $fetcher = new JournalPageFetcher;
        foreach (['http://journal.example.org', 'https://user:pass@journal.example.org/', 'https://localhost/', 'https://journal.example.org:8443/'] as $url) {
            try {
                $fetcher->host($url);
                $this->fail('Unsafe URL was accepted: '.$url);
            } catch (\RuntimeException $e) {
                $this->assertSame('invalid_source_url', $e->getMessage());
            }
        }
        $this->assertFalse($fetcher->allowed("User-agent: *\nDisallow: /about\n", '/about/submissions'));
        $this->assertTrue($fetcher->allowed("User-agent: *\nDisallow: /about\nAllow: /about/public\n", '/about/public'));
    }

    public function test_scope_extractor_ignores_ojs_navigation_paragraph(): void
    {
        $html = '<html><body><p>Editorial Team Publication Ethics Focus &amp; Scope Author Guidelines Peer-Review Process Indexing Contact</p>'
            .'<main><h2>Focus and Scope</h2><p>This journal publishes empirical research on vocational education and technology in classrooms.</p></main></body></html>';
        $quotes = (new JournalEvidenceExtractor)->extract($html, 'scope');
        $this->assertCount(1, $quotes);
        $this->assertStringContainsString('empirical research', $quotes[0]);
    }

    public function test_ai_only_keeps_exact_evidence_and_uses_shared_budget(): void
    {
        config(['m19.ai_enabled' => true, 'semantic.enabled' => true, 'semantic.key' => 'test-key',
            'm15.enabled' => true, 'm19.ai_monthly_limit' => 2]);
        $html = '<html><body><nav><p>Publication fee is 999 USD per article.</p></nav>'
            .'<main><p>Publication fee is 500 USD per article.</p>'
            .'<p>Review duration is 4 weeks after submission.</p></main></body></html>';
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['quotes' => [
            'Publication fee is 500 USD per article.',
            'Publication fee is 999 USD per article.',
            'The journal is free of charge.',
            'Review duration is 4 weeks after submission.',
        ]])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 40]], 200)]);
        $quotes = app(JournalAiExtractor::class)->extract($html, 'fee');
        $this->assertSame(['Publication fee is 500 USD per article.'], $quotes);
        $this->assertSame(1, DB::table('m15_ai_usage')->sum('request_count'));
        DB::table('m19_runs')->insert(['source_id' => DB::table('m19_sources')->insertGetId([
            'journal_id' => $this->journal()->id, 'url' => 'https://journal.example.org/about',
            'field' => 'fee', 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]), 'status' => 'changed', 'ai_status' => 'completed', 'created_at' => now()]);
        DB::table('m19_runs')->insert(['source_id' => DB::table('m19_sources')->value('id'),
            'status' => 'changed', 'ai_status' => 'failed', 'created_at' => now()]);
        $this->assertFalse(app(JournalAiExtractor::class)->ready());
        Http::assertSentCount(1);
    }

    public function test_ai_retries_one_invalid_response_and_bills_both_requests(): void
    {
        config(['m19.ai_enabled' => true, 'semantic.enabled' => true, 'semantic.key' => 'test-key',
            'm15.enabled' => true, 'm19.ai_max_completion_tokens' => 2000]);
        Http::fake(['*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{"unexpected":true}']]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]], 200)
            ->push(['choices' => [['message' => ['content' => '{"quotes":["Publication fee is 500 USD per article, payable after acceptance."]}']]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 40]], 200)]);

        $quotes = app(JournalAiExtractor::class)->extract('<html><body><p>Publication fee is 500 USD per article, payable after acceptance.</p></body></html>', 'fee');

        $this->assertSame(['Publication fee is 500 USD per article, payable after acceptance.'], $quotes);
        Http::assertSentCount(2);
        $this->assertSame(2, DB::table('m15_ai_usage')->sum('request_count'));
    }

    public function test_ai_failure_preserves_rule_based_candidate(): void
    {
        config(['m19.enabled' => true, 'm19.ai_enabled' => true, 'semantic.enabled' => true,
            'semantic.key' => 'test-key', 'm15.enabled' => true]);
        $journal = $this->journal();
        DB::table('m19_sources')->insert(['journal_id' => $journal->id,
            'url' => 'https://journal.example.org/about', 'field' => 'fee', 'enabled' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><p>Publication fee is 500 USD per article, payable only after acceptance.</p></body></html>'];
            }
        });
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->assertSame(1, app(JournalMonitoringAgent::class)->tick()['changed']);
        $this->assertDatabaseHas('m19_runs', ['ai_status' => 'failed']);
        $this->assertDatabaseHas('m19_candidates', ['quote' => 'Publication fee is 500 USD per article, payable only after acceptance.']);
        $this->assertDatabaseCount('journal_observations', 0);
    }

    public function test_ai_adds_verified_quote_when_rule_extractor_misses_it(): void
    {
        config(['m19.enabled' => true, 'm19.ai_enabled' => true, 'semantic.enabled' => true,
            'semantic.key' => 'test-key', 'm15.enabled' => true]);
        $journal = $this->journal();
        DB::table('m19_sources')->insert(['journal_id' => $journal->id,
            'url' => 'https://journal.example.org/about', 'field' => 'fee', 'enabled' => true,
            'created_at' => now(), 'updated_at' => now()]);
        $this->app->instance(JournalPageFetcher::class, new class extends JournalPageFetcher {
            public function fetch(string $url): array
            {
                return ['status' => 200, 'body' => '<html><body><main><p>The article processing charge is waived for every author after acceptance.</p></main></body></html>'];
            }
        });
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' =>
            '{"quotes":["The article processing charge is waived for every author after acceptance."]}']]],
        ], 200)]);
        $this->assertSame(1, app(JournalMonitoringAgent::class)->tick()['changed']);
        $this->assertDatabaseHas('m19_candidates', ['quote' => 'The article processing charge is waived for every author after acceptance.', 'status' => 'pending']);
        $this->assertDatabaseHas('m19_runs', ['ai_status' => 'completed']);
        $this->assertDatabaseCount('journal_observations', 0);
    }
}
