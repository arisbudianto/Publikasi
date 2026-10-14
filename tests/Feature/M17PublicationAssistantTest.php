<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\M18Guide;
use App\Models\User;
use App\Services\DinoikiResearchAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class M17PublicationAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_catalog_and_guides_reach_the_model(): void
    {
        $author = User::factory()->create();
        $journal = Journal::create(['name' => 'Jurnal Pendidikan Dasar', 'publisher' => 'Penerbit Uji',
            'issn' => '2088-2866', 'subject' => 'Pendidikan dasar', 'scope_summary' => 'Riset pembelajaran sekolah dasar',
            'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org',
            'checked_at' => today(), 'status' => 'published']);
        Journal::create(['name' => 'Jurnal Pendidikan Rahasia', 'publisher' => 'Penerbit Uji',
            'issn' => '2088-2867', 'subject' => 'Pendidikan dasar', 'scope_summary' => 'Belum terbit',
            'scope_source_url' => 'https://example.org/private', 'official_url' => 'https://example.org',
            'checked_at' => today(), 'status' => 'draft']);
        M18Guide::create(['slug' => 'pendidikan-dasar', 'title' => 'Panduan pendidikan dasar',
            'body' => str_repeat('Periksa cakupan jurnal. ', 6), 'author_id' => $author->id,
            'reviewed_at' => today(), 'published_at' => now()]);
        M18Guide::create(['slug' => 'pendidikan-rahasia', 'title' => 'Panduan pendidikan rahasia',
            'body' => str_repeat('Materi belum terbit. ', 6), 'author_id' => $author->id,
            'reviewed_at' => today(), 'published_at' => null]);

        $mock = Mockery::mock(DinoikiResearchAi::class);
        $mock->shouldReceive('ready')->once()->andReturn(true);
        $mock->shouldReceive('answerCatalogQuestion')->once()->withArgs(function ($question, $sources) use ($journal) {
            $ids = array_column($sources, 'id');
            return str_contains($question, 'pendidikan dasar') && in_array('journal:'.$journal->id, $ids, true)
                && in_array('guide:1', $ids, true) && count($sources) === 2;
        })->andReturn([['text' => 'Cakupannya meliputi sekolah dasar.', 'source_ids' => ['journal:'.$journal->id]]]);
        $this->app->instance(DinoikiResearchAi::class, $mock);

        $this->post('/asisten', ['question' => 'Apa jurnal untuk penelitian pendidikan dasar?'])->assertOk()
            ->assertSee('Cakupannya meliputi sekolah dasar.')
            ->assertSee('Panduan pendidikan dasar')->assertDontSee('rahasia');
    }

    public function test_unmatched_question_does_not_call_ai(): void
    {
        $this->post('/asisten', ['question' => 'Pertanyaan tentang topik yang tak tersedia'])->assertOk()
            ->assertSee('Belum ada data katalog');
    }

    public function test_explicit_issn_answers_scope_and_unknown_fee_without_unrelated_journals_or_ai(): void
    {
        Journal::create(['name' => 'Indonesian Journal of Applied Linguistics', 'publisher' => 'Universitas Pendidikan Indonesia',
            'issn' => '2301-9468', 'subject' => 'Pendidikan bahasa',
            'scope_summary' => 'Kajian linguistik terapan dan pembelajaran bahasa.',
            'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org',
            'checked_at' => today(), 'status' => 'published']);
        Journal::create(['name' => 'Jurnal Pendidikan Lain', 'publisher' => 'Penerbit Uji',
            'issn' => '2088-2866', 'subject' => 'Pendidikan umum', 'scope_summary' => 'Penelitian pendidikan.',
            'scope_source_url' => 'https://example.org/other', 'official_url' => 'https://example.org',
            'checked_at' => today(), 'status' => 'published']);
        $mock = Mockery::mock(DinoikiResearchAi::class);
        $mock->shouldNotReceive('ready');
        $mock->shouldNotReceive('answerCatalogQuestion');
        $this->app->instance(DinoikiResearchAi::class, $mock);

        $this->post('/asisten', ['question' => 'Apa cakupan jurnal ISSN 2301-9468 dan apakah biaya publikasinya diketahui?'])
            ->assertOk()->assertSee('Kajian linguistik terapan dan pembelajaran bahasa.')
            ->assertSee('belum tercatat dalam observasi katalog yang berlaku')
            ->assertDontSee('Jurnal Pendidikan Lain')->assertDontSee('Jawaban belum dapat diverifikasi');
    }
}
