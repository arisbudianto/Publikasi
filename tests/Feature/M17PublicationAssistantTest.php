<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\M18Guide;
use App\Models\M18Proposal;
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

    public function test_journal_context_and_guide_page_offer_the_assistant(): void
    {
        $journal = Journal::create(['name' => 'Jurnal Pendidikan Vokasi', 'publisher' => 'Penerbit Uji',
            'issn' => '2088-2866', 'subject' => 'Vokasi', 'scope_summary' => 'Pembelajaran vokasi',
            'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org',
            'checked_at' => today(), 'status' => 'published']);

        $this->get(route('catalog.show', $journal))->assertOk()->assertDontSee('Tanya tentang jurnal ini');
        $this->get(route('assistant.index', ['journal' => $journal->id]))->assertOk()
            ->assertSee('ISSN 2088-2866');
        $this->get(route('guides.index'))->assertOk()->assertSee('Panduan publikasi');
    }

    public function test_feedback_requires_login_and_enters_curation_without_storing_conversation(): void
    {
        $input = ['feedback' => 'Biaya pada sumber resmi perlu diperiksa kembali.'];
        $this->post(route('proposals.assistant-feedback'), $input)->assertRedirect(route('account.login'));
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('proposals.assistant-feedback'), ['feedback' => 'pendek'])
            ->assertSessionHasErrors('feedback');
        $this->post(route('proposals.assistant-feedback'), $input)->assertRedirect(route('proposals.mine'));

        $proposal = M18Proposal::firstOrFail();
        $this->assertSame('assistant_feedback', $proposal->field);
        $this->assertSame(route('assistant.index'), $proposal->source_url);
        $this->assertSame($input['feedback'], $proposal->proposed_value);
        $this->assertDatabaseCount('m18_proposal_events', 1);
        $this->assertDatabaseCount('journal_observations', 0);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.proposals.show', $proposal))->assertOk()
            ->assertSee('Laporan telah ditinjau');
        $this->post(route('admin.proposals.review', $proposal), [
            'decision' => 'reviewed', 'decision_note' => 'Kami akan memeriksa kembali bukti yang dilaporkan.',
        ])->assertRedirect();
        $this->get(route('admin.proposals.show', $proposal))->assertDontSee('Catat sebagai diterapkan');
    }
}
