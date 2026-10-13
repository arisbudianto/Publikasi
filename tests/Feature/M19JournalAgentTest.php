<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\User;
use App\Services\JournalEvidenceExtractor;
use App\Services\JournalMonitoringAgent;
use App\Services\JournalPageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(0, app(JournalMonitoringAgent::class)->tick()['checked']);
        DB::table('m19_sources')->where('id', $source)->update(['next_check_at' => now()->subMinute()]);
        $this->assertSame(0, app(JournalMonitoringAgent::class)->tick()['changed']);
        $this->assertDatabaseCount('m19_candidates', 1);
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
}
