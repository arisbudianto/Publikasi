<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\ScopusSource;
use App\Services\ArticleEvidence;
use App\Services\DinoikiEmbeddings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemanticMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['semantic.enabled' => true, 'semantic.key' => 'FAKE_TEST_KEY']);
        Http::preventStrayRequests();
    }

    private function journal(string $status = 'published'): Journal
    {
        return Journal::create(['name' => 'Jurnal Vokasi Uji', 'publisher' => 'Uji', 'issn' => '2088-2866', 'subject' => 'Vokasi', 'scope_summary' => 'Pelatihan kejuruan', 'official_url' => 'https://example.org', 'scope_source_url' => 'https://example.org/scope', 'checked_at' => '2026-01-01', 'status' => $status]);
    }

    private function input(): array
    {
        return ['title' => 'Technical skills training', 'abstract' => str_repeat('Vocational learning through practical technical skills. ', 3), 'context' => 'Sekolah kejuruan', 'consent' => '1'];
    }

    private function fake(bool $unavailable = false): void
    {
        Http::fake([
            'ai.dinoiki.com/*' => function ($request) {
                return Http::response(['data' => array_map(fn ($i) => ['index' => $i, 'embedding' => [1, 0]], array_keys($request['input']))]);
            },
            'api.crossref.org/*' => $unavailable ? Http::response([], 503) : Http::response(['message' => ['items' => [['DOI' => '10.1234/real-record', 'title' => ['Technical learning evidence'], 'ISSN' => ['2088-2866'], 'published' => ['date-parts' => [[2026]]]]]]]),
        ]);
    }

    public function test_semantic_flow_provenance_and_no_manuscript_flash(): void
    {
        $this->journal();
        $this->fake();
        $this->post('/cocokkan-ai', $this->input())->assertOk()->assertSee('Jurnal Vokasi Uji')->assertSee('Technical learning evidence')->assertSee('10.1234/real-record')->assertSee('Crossref')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull(session('_old_input'));
        $this->assertDatabaseCount('catalog_audits', 0);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'ai.dinoiki.com') && $r['model'] === 'text-embedding-3-small');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.crossref.org') && ! str_contains($r->url(), 'Technical'));
    }

    public function test_main_ai_page_has_requested_fields_and_index_filters_separate_candidates(): void
    {
        $this->journal();
        ScopusSource::create(['source_record_id' => 91, 'source_title' => 'Technical Learning Journal',
            'issn' => '12345678', 'status' => 'Active', 'source_type' => 'Journal',
            'snapshot_month' => '2026-09', 'is_current' => true]);
        $this->fake();
        $this->get('/cocokkan-ai')->assertOk()->assertSee('Mencari jurnal yang tepat dengan Bantuan AI')
            ->assertSee('Indeks jurnal')->assertSee('SINTA atau Scopus');
        $this->post('/cocokkan-ai', $this->input() + ['index' => 'scopus'])
            ->assertOk()->assertSee('Technical Learning Journal')->assertSee('bukan penilaian AI atas scope');
        $this->post('/cocokkan-ai', $this->input() + ['index' => 'sinta'])
            ->assertOk()->assertDontSee('Technical Learning Journal');
        $this->post('/cocokkan-ai', $this->input() + ['index' => 'invalid'])
            ->assertStatus(422);
    }

    public function test_missing_consent_and_disabled_ai_never_call_external(): void
    {
        $this->journal();
        $input = $this->input();
        unset($input['consent']);
        $this->post('/cocokkan-ai', $input)->assertStatus(422);
        Http::assertNothingSent();
        config(['semantic.enabled' => false]);
        $this->post('/cocokkan-ai', $this->input())->assertStatus(503);
        Http::assertNothingSent();
        $this->get('/cocokkan-ai')->assertOk()->assertSee('belum diaktifkan');
    }

    public function test_provider_errors_are_redacted_and_zero_vectors_rejected(): void
    {
        $this->journal();
        Http::fake(['ai.dinoiki.com/*' => Http::response(['secret' => 'FAKE_TEST_KEY', 'request' => $this->input()], 401)]);
        $this->post('/cocokkan-ai', $this->input())->assertStatus(503)->assertDontSee('FAKE_TEST_KEY')->assertSee('Layanan AI tidak tersedia');
        Http::fake(['ai.dinoiki.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0, 0]], ['index' => 1, 'embedding' => [0, 0]]]])]);
        $this->post('/cocokkan-ai', $this->input())->assertStatus(503);
    }

    public function test_private_journals_excluded_and_catalog_bound(): void
    {
        $this->journal('draft');
        $this->fake();
        $this->post('/cocokkan-ai', $this->input())->assertOk()->assertDontSee('Jurnal Vokasi Uji');
        Http::assertNothingSent();
        Journal::first()->update(['status' => 'published']);
        config(['semantic.catalog_limit' => 0]);
        $this->post('/cocokkan-ai', $this->input())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_article_failure_does_not_fail_scope_results(): void
    {
        $this->journal();
        $this->fake(true);
        $this->post('/cocokkan-ai', $this->input())->assertOk()->assertSee('Jurnal Vokasi Uji')->assertSee('Sumber artikel sedang tidak tersedia');
    }

    public function test_evidence_rejects_wrong_issn_and_bad_doi(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response(['message' => ['items' => [
            ['DOI' => 'javascript:alert(1)', 'title' => ['Bad'], 'ISSN' => ['2088-2866']],
            ['DOI' => '10.1234/other', 'title' => ['Other'], 'ISSN' => ['1111-1111']],
        ]]])]);
        $this->assertSame([], app(ArticleEvidence::class)->forIssn('2088-2866')['articles']);
    }

    public function test_daily_limit_and_similarity(): void
    {
        $this->journal();
        $this->fake();
        config(['semantic.daily_limit' => 1]);
        $this->post('/cocokkan-ai', $this->input())->assertOk();
        $this->post('/cocokkan-ai', $this->input())->assertStatus(429);
        $this->assertEquals(0, app(DinoikiEmbeddings::class)->similarity([1, 0],[0, 1]));
    }
}
