<?php

namespace Tests\Feature;

use App\Services\CrossrefReferenceChecker;
use App\Services\ReferenceTextParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReferenceCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.crossref.mailto' => 'test@example.org']);
        Cache::flush();
    }

    public function test_reference_page_discloses_crossref_submission_and_has_no_cached_input(): void
    {
        $this->get('/cek-referensi')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('dikirim satu per satu ke Crossref')->assertSee('Jangan masukkan daftar pustaka yang bersifat rahasia.');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_doi_lookup_compares_fields_and_reports_crossref_update_notice(): void
    {
        Http::fake(['https://api.crossref.org/works/*' => Http::response(['message' => [
            'DOI' => '10.1234/example', 'title' => ['A verified study'],
            'author' => [['family' => 'Smith', 'given' => 'Jane']], 'published' => ['date-parts' => [[2021]]],
            'container-title' => ['Education Review'],
            'updated-by' => [['type' => 'retraction', 'label' => 'Retraction', 'source' => 'retraction-watch', 'DOI' => '10.1234/notice', 'updated' => ['date-parts' => [[2024, 3, 2]]]],],
            'abstract' => '<p>must not be cached</p>',
        ]])]);

        $response = $this->postJson('/cek-referensi/periksa-satu', [
            'reference' => 'Jones, A. (2020). A different study. Journal. https://doi.org/10.1234/example', 'consent' => true,
        ]);
        $response->assertOk()->assertJsonPath('match_status', 'matched_with_difference')
            ->assertJsonPath('update_status', 'retracted')->assertJsonPath('updates.0.source', 'retraction-watch')
            ->assertJsonPath('updates.0.notice_url', 'https://doi.org/10.1234/notice');
        Http::assertSent(fn ($request) => str_contains($request->url(), '10.1234%2Fexample') || str_contains($request->url(), '10.1234/example'));
        $cached = Cache::get('reference-crossref-doi-v1-'.hash('sha256', '10.1234/example'));
        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('abstract', $cached);
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(session('_old_input'));
    }

    public function test_search_without_doi_returns_possible_match_and_does_not_cache_query(): void
    {
        Http::fake(['https://api.crossref.org/works*' => Http::response(['message' => ['items' => [[
            'DOI' => '10.9999/candidate', 'title' => ['The title of a study'], 'author' => [['family' => 'Smith']],
            'published' => ['date-parts' => [[2022]]], 'container-title' => ['Example Journal'],
        ]]]])]);

        $response = $this->postJson('/cek-referensi/periksa-satu', ['reference' => 'Smith, J. (2022). The title of a study. Journal.', 'consent' => true]);
        $response->assertOk()->assertJsonPath('match_status', 'possible_match')->assertJsonPath('title_similarity', 100);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'query.bibliographic=') && str_contains($request->url(), 'mailto=test%40example.org'));
        $this->assertNull(Cache::get('reference-check:search-query'));
    }

    public function test_consent_and_size_are_validated_before_crossref_is_called(): void
    {
        Http::fake();
        $this->postJson('/cek-referensi/periksa-satu', ['reference' => 'private list'])->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/cek-referensi/periksa-satu', ['reference' => str_repeat('x', 2001), 'consent' => true])->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertNull(session('_old_input'));
    }

    public function test_reference_parser_splits_numbered_and_wrapped_entries(): void
    {
        $parser = new ReferenceTextParser;
        $this->assertSame([
            'Smith, J. (2022). First title. Journal.',
            'Jones, A. (2020). Second title. Journal.',
        ], $parser->split("1. Smith, J. (2022). First title.\n   Journal.\n2) Jones, A. (2020). Second title. Journal."));
        $this->assertSame('10.1234/abc', $parser->fields('Smith (2020). Title. doi:10.1234/abc')['doi']);
    }
}
