<?php

namespace Tests\Feature;

use App\Models\MatchHistory;
use App\Models\User;
use App\Services\DinoikiResearchAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class M15ResearchPositioningTest extends TestCase
{
    use RefreshDatabase;

    private const WORK_ID = 'https://openalex.org/W1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'm15.enabled' => true,
            'm15.monthly_budget_usd' => 5,
            'm15.monthly_analysis_limit' => 20,
            'semantic.enabled' => true,
            'semantic.key' => 'test-dinoiki-key',
            'services.openalex.key' => 'test-openalex-key',
            'services.openalex.mailto' => 'test@example.org',
        ]);
    }

    public function test_route_is_restricted_to_authenticated_accounts(): void
    {
        $this->get('/posisi-riset')->assertRedirect(route('account.login'));
    }

    public function test_third_party_consent_is_required_before_any_provider_request(): void
    {
        Http::fake();
        $input = $this->input();
        unset($input['consent']);
        $this->actingAs(User::factory()->create())
            ->post('/posisi-riset/siapkan', $input)
            ->assertUnprocessable()
            ->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
        $this->assertNull(session('_old_input'));
    }

    public function test_english_analysis_returns_only_source_bound_claims_and_measures_usage(): void
    {
        $this->fakeProviders($this->analysisPayload([
            'dimensions' => [[
                'dimension' => 'topic', 'level' => 'high', 'statement' => 'Topik berdekatan.', 'work_ids' => [self::WORK_ID],
            ]],
            'differentiators' => [[
                'statement' => 'Konteks yang diuji berbeda.', 'work_ids' => [self::WORK_ID, 'https://openalex.org/W9999999999'],
            ]],
            'similarity_risks' => [[
                'statement' => 'Kemiripan perlu diperiksa.', 'work_ids' => ['https://openalex.org/W9999999999'],
            ]],
            'strengthening_directions' => [],
            'novelty_draft' => ['statement' => 'Draf klaim hati-hati.', 'work_ids' => [self::WORK_ID]],
        ]));

        $this->actingAs(User::factory()->create())
            ->post('/posisi-riset/siapkan', $this->input(['language' => 'en']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, noarchive')
            ->assertSee('Closest publication:')
            ->assertSee('Contemporary Research Journal')
            ->assertSee('Topik berdekatan.')
            ->assertSee('Draf klaim hati-hati.')
            ->assertDontSee('Kemiripan perlu diperiksa.');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openalex.org/works')
            && ! str_contains($request->url(), 'test-openalex-key')
            && $request->hasHeader('Authorization', 'Bearer test-openalex-key')
            && str_contains(urldecode($request->url()), ' OR ')
            && ! str_contains(urldecode($request->url()), 'Pretest'));
        $this->assertDatabaseHas('m15_ai_usage', ['analysis_count' => 1, 'openalex_requests' => 1, 'request_count' => 2]);
        $this->assertDatabaseCount('match_histories', 0);
    }

    public function test_indonesian_translation_is_shown_for_edit_before_search(): void
    {
        $chat = Http::sequence()
            ->push([
                'choices' => [['message' => ['content' => 'English academic translation for the research title and abstract.']]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20],
            ])
            ->push([
                'choices' => [['message' => ['content' => json_encode($this->analysisPayload([]))]]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 40],
            ]);
        Http::fake([
            'https://api.openalex.org/works*' => Http::response([
                'meta' => ['cost_usd' => 0.001],
                'results' => [[
                    'id' => self::WORK_ID, 'title' => 'Related English publication', 'display_name' => 'Related English publication',
                    'publication_year' => 2024, 'primary_location' => [], 'abstract_inverted_index' => ['This' => [0], 'study' => [1], 'tests' => [2]],
                ]],
            ]),
            'https://ai.dinoiki.com/v1/embeddings' => Http::response([
                'data' => [['index' => 0, 'embedding' => [1, 0]], ['index' => 1, 'embedding' => [0.9, 0.1]]],
                'usage' => ['prompt_tokens' => 80],
            ]),
            'https://ai.dinoiki.com/v1/chat/completions' => $chat,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/posisi-riset/siapkan', $this->input(['language' => 'id', 'consent' => '1']))
            ->assertOk()
            ->assertSee('English academic translation for the research title and abstract.')
            ->assertSee('Tinjau teks untuk pencarian');

        Http::assertSentCount(1);
        $invalid = $this->input(['language' => 'id']);
        unset($invalid['consent']);
        $this->post('/posisi-riset/analisis', $invalid + [
            'translated_query' => 'Edited English query with corrected terms and methods.',
        ])->assertUnprocessable();
        Http::assertSentCount(1);

        $this->post('/posisi-riset/analisis', $this->input(['language' => 'id']) + [
            'translated_query' => 'Edited English query with corrected terms and methods.',
            'consent' => '1',
        ])->assertOk()->assertSee('Related English publication');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openalex.org/works')
            && str_contains(urldecode($request->url()), 'edited OR english')
            && ! str_contains(urldecode($request->url()), 'corrected terms and methods'));
        $this->assertDatabaseHas('m15_ai_usage', ['analysis_count' => 1, 'request_count' => 3]);
        $this->assertNull(session('_old_input'));
    }

    public function test_candidates_without_abstract_are_visible_but_not_sent_for_dimension_analysis(): void
    {
        $this->fakeProviders($this->analysisPayload([]), false);

        $this->actingAs(User::factory()->create())
            ->post('/posisi-riset/siapkan', $this->input(['language' => 'en']))
            ->assertOk()
            ->assertSee('Contemporary Research Journal')
            ->assertSee('Abstrak tidak tersedia')
            ->assertSee('variabel atau metode');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/chat/completions'));
    }

    public function test_empty_search_preserves_title_and_timestamp_without_claiming_fallback_analysis(): void
    {
        Http::fake(['https://api.openalex.org/works*' => Http::response(['meta' => ['cost_usd' => 0.001], 'results' => []])]);

        $this->actingAs(User::factory()->create())
            ->post('/posisi-riset/siapkan', $this->input())
            ->assertOk()
            ->assertSee('Posisi riset: Digital scaffolding for vocational reading')
            ->assertSee('TANPA KANDIDAT')
            ->assertSee('0 kandidat dari OpenAlex, dianalisis 20');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'ai.dinoiki.com'));
    }

    public function test_opt_in_history_saves_result_metadata_but_never_the_abstract(): void
    {
        $abstract = $this->input()['abstract'];
        $this->fakeProviders($this->analysisPayload([]));
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posisi-riset/siapkan', $this->input(['language' => 'en', 'save_history' => '1']))->assertOk()
            ->assertSee('Abstrak tidak disimpan.');

        $this->assertDatabaseHas('match_histories', ['user_id' => $user->id, 'mode' => 'position']);
        $saved = json_encode(MatchHistory::where('user_id', $user->id)->first()->candidates, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($abstract, $saved);
        $this->actingAs($user)->get('/akun')->assertOk()->assertSee('Posisi riset M15');
    }

    public function test_chat_omits_temperature_rejected_by_the_provider(): void
    {
        Http::fake([
            'https://ai.dinoiki.com/v1/chat/completions' => function ($request) {
                if (array_key_exists('temperature', $request->data())) {
                    return Http::response(['error' => ['code' => 'unsupported_value', 'param' => 'temperature']], 400);
                }

                return Http::response([
                    'choices' => [['message' => ['content' => 'Translated academic research idea.']]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 8],
                ]);
            },
        ]);

        $this->assertSame('Translated academic research idea.', app(DinoikiResearchAi::class)->translate('Ide riset untuk diterjemahkan.'));
        Http::assertSent(fn ($request) => ! array_key_exists('temperature', $request->data()) && $request['store'] === false);
        $this->assertDatabaseHas('m15_ai_usage', ['request_count' => 1, 'reserved_usd' => 0]);
    }

    public function test_provider_failure_keeps_active_pilot_form_available(): void
    {
        Http::fake(['https://ai.dinoiki.com/v1/chat/completions' => Http::response(['error' => ['code' => 'unavailable']], 503)]);

        $this->actingAs(User::factory()->create())
            ->post('/posisi-riset/siapkan', $this->input(['language' => 'id']))
            ->assertStatus(503)
            ->assertSee('Layanan analisis sedang tidak tersedia.')
            ->assertSee('Judul ide riset')
            ->assertSee('Digital scaffolding for vocational reading')
            ->assertDontSee('Pilot belum aktif.');

        $this->assertNull(session('_old_input'));
        $this->assertDatabaseHas('m15_ai_usage', ['reserved_usd' => 0]);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Digital scaffolding for vocational reading',
            'abstract' => 'This study examines a learning intervention, research variables, classroom methods, and the vocational education context in sufficient detail for a pilot search.',
            'field' => 'vocational education',
            'year_from' => '2020',
            'year_to' => '2026',
            'language' => 'en',
            'consent' => '1',
        ], $overrides);
    }

    private function fakeProviders(array $analysis, bool $withAbstract = true): void
    {
        $work = [
            'id' => self::WORK_ID,
            'doi' => 'https://doi.org/10.1234/example',
            'title' => 'Closest publication: digital scaffolding in vocational education',
            'display_name' => 'Closest publication: digital scaffolding in vocational education',
            'publication_year' => 2024,
            'primary_location' => ['landing_page_url' => 'https://example.org/work', 'source' => ['display_name' => 'Contemporary Research Journal']],
            'abstract_inverted_index' => $withAbstract ? [
                'This' => [0], 'study' => [1], 'examines' => [2], 'digital' => [3], 'scaffolding' => [4], 'in' => [5],
                'vocational' => [6], 'education' => [7], 'with' => [8], 'classroom' => [9], 'methods' => [10],
            ] : null,
        ];
        Http::fake([
            'https://api.openalex.org/works*' => Http::response(['meta' => ['cost_usd' => 0.001], 'results' => [$work]]),
            'https://ai.dinoiki.com/v1/embeddings' => Http::response([
                'data' => [['index' => 0, 'embedding' => [1, 0]], ['index' => 1, 'embedding' => [0.9, 0.1]]],
                'usage' => ['prompt_tokens' => 80],
            ]),
            'https://ai.dinoiki.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode($analysis, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 40],
            ]),
        ]);
    }

    private function analysisPayload(array $overrides = []): array
    {
        return array_merge([
            'summary' => 'Satu publikasi metadata yang tersedia berdekatan.',
            'dimensions' => [],
            'differentiators' => [],
            'similarity_risks' => [],
            'strengthening_directions' => [],
            'novelty_draft' => null,
        ], $overrides);
    }
}
