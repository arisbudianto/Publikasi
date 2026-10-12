<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Services\BasicMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicMatchTest extends TestCase
{
    use RefreshDatabase;

    private function journal(int $n, string $scope, string $status = 'published'): Journal
    {
        return Journal::create(['name' => "Kandidat $n", 'publisher' => 'Fixture', 'issn' => "0000-000$n", 'subject' => 'Pendidikan', 'scope_summary' => $scope, 'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org', 'checked_at' => '2026-01-01', 'status' => $status]);
    }

    private function input(array $extra = []): array
    {
        return array_merge(['title' => 'Vocational curriculum development', 'abstract' => str_repeat('Vocational curriculum and training for technical schools. ', 3), 'context' => 'Pelatihan vokasi'], $extra);
    }

    public function test_bilingual_match_reason_and_provenance(): void
    {
        $this->journal(1, 'Kurikulum dan pelatihan pendidikan vokasi.');
        $this->post('/cocokkan', $this->input())->assertOk()->assertSee('Kandidat 1')->assertSee('vokasi')->assertSee('kurikulum')->assertSee('https://example.org/scope')->assertSee('01/01/2026')->assertSee('Konteks belum diverifikasi')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('catalog_audits', 0);
        $this->assertDatabaseCount('journals', 1);
        $this->assertNull(session('_old_input'));
    }

    public function test_title_weight_stable_order_limit_and_private_exclusion(): void
    {
        $this->journal(1, 'Bahasa sains');
        $this->journal(2, 'Vokasi');
        $this->journal(3, 'Vokasi', 'draft');
        $this->journal(4, 'Vokasi', 'archived');
        $matcher = new BasicMatcher;
        $results = $matcher->match('Vocational training', 'language science', '');
        $this->assertSame([2, 1], array_map(fn ($r) => $r['journal']->id, $results));
        foreach (range(5, 16) as $n) {
            $this->journal($n, 'Vokasi');
        }
        $results = $matcher->match('Vocational training', 'language science', '');
        $this->assertCount(10, $results);
        $this->assertSame(2, $results[0]['journal']->id);
    }

    public function test_context_alone_does_not_create_candidate(): void
    {
        $this->journal(1, 'Vokasi');
        $this->assertSame([], (new BasicMatcher)->match('astronomy galaxies', 'galaxies stars', 'vokasi'));
        $this->post('/cocokkan', $this->input(['title' => 'Galaxies and astronomy', 'abstract' => str_repeat('Stars and galaxies in astronomy. ', 4)]))->assertOk()->assertSee('Belum ada kandidat');
    }

    public function test_invalid_payload_does_not_flash_abstract(): void
    {
        $abstract = str_repeat('PRIVATE_MANUSCRIPT ', 10);
        $this->post('/cocokkan', ['title' => 'short', 'abstract' => $abstract])->assertStatus(422)->assertDontSee($abstract)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull(session('_old_input'));
        $this->post('/cocokkan', $this->input(['abstract' => str_repeat('a', 10001)]))->assertStatus(422);
        $this->post('/cocokkan', $this->input(['context' => ['invalid']]))->assertStatus(422);
    }

    public function test_generic_terms_repetition_and_html_are_not_false_evidence(): void
    {
        $matcher = new BasicMatcher;
        $this->assertSame(['vokasi'], $matcher->terms('vokasi vocational vocational education research'));
        $this->journal(1, 'pendidikan penelitian hasil metode');
        $this->assertSame([], $matcher->match('pendidikan penelitian', 'education research', ''));
        $this->journal(2, 'vokasi');
        $this->post('/cocokkan', $this->input(['title' => '<script>alert(1)</script> vocational']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_form_public_and_post_rate_limited(): void
    {
        $this->get('/cocokkan')->assertOk()->assertSee('Abstrak');
        for ($i = 0; $i < 20; $i++) {
            $this->post('/cocokkan',$this->input())->assertOk();
        }
        $this->post('/cocokkan',$this->input())->assertStatus(429);
    }
}
