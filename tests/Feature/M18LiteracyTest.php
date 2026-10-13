<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\M18Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class M18LiteracyTest extends TestCase
{
    use RefreshDatabase;

    private function journal(): Journal
    {
        return Journal::create(['name' => 'Jurnal Vokasi Uji', 'publisher' => 'Penerbit Uji', 'issn' => '2088-2866',
            'subject' => 'Vokasi', 'scope_summary' => 'Penelitian pendidikan vokasi',
            'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org',
            'checked_at' => today(), 'status' => 'published']);
    }

    private function proposalInput(Journal $journal): array
    {
        return ['journal_id' => $journal->id, 'field' => 'fee', 'proposed_value' => 'Biaya 500000 IDR',
            'source_url' => 'https://example.org/apc', 'note' => 'Lihat halaman biaya publikasi resmi.'];
    }

    public function test_guides_are_written_by_admin_and_drafts_are_private(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $visitor = User::factory()->create();
        $data = ['slug' => 'biaya-publikasi', 'title' => 'Memeriksa biaya publikasi jurnal',
            'body' => str_repeat('Periksa informasi biaya pada sumber yang tercantum. ', 3),
            'reviewed_at' => today()->format('Y-m-d'), 'status' => 'draft'];

        $this->get('/panduan')->assertOk()->assertSee('Belum ada panduan');
        $this->actingAs($visitor)->post(route('admin.guides.store'), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.guides.store'), $data)->assertRedirect();
        $this->get(route('guides.show', $data['slug']))->assertNotFound();
        $guide = \App\Models\M18Guide::firstOrFail();
        $this->put(route('admin.guides.update', $guide), array_merge($data, ['status' => 'published',
            'body' => $data['body'].' <script>alert(1)</script>']))->assertRedirect();
        $this->get(route('guides.show', $data['slug']))->assertOk()->assertSee('Ditinjau')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_proposals_require_login_https_and_never_change_catalog_on_submission(): void
    {
        $journal = $this->journal();
        $data = $this->proposalInput($journal);
        $this->get(route('proposals.create', ['journal' => $journal->id]))->assertRedirect(route('account.login'));
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('proposals.store'), array_merge($data,
            ['source_url' => 'http://example.org/apc']))->assertSessionHasErrors('source_url');
        $this->assertDatabaseCount('m18_proposals', 0);
        $this->post(route('proposals.store'), $data)->assertRedirect(route('proposals.mine'));
        $this->assertDatabaseCount('m18_proposals', 1);
        $this->assertDatabaseCount('journal_observations', 0);
        $this->assertDatabaseHas('m18_proposals', ['user_id' => $user->id, 'journal_id' => $journal->id, 'status' => 'new']);
        $this->get(route('proposals.mine'))->assertOk()->assertSee('Baru');
        $this->assertDatabaseHas('m18_proposal_events', ['from_status' => null, 'to_status' => 'new']);
    }

    public function test_admin_review_and_observation_publication_are_audited(): void
    {
        $journal = $this->journal();
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user)->post(route('proposals.store'), $this->proposalInput($journal))->assertRedirect();
        $proposal = M18Proposal::firstOrFail();
        $this->get(route('admin.proposals.show', $proposal))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.proposals.review', $proposal),
            ['decision' => 'reviewed', 'decision_note' => 'Sumber perlu dicocokkan dengan halaman resmi jurnal.'])->assertRedirect();
        $this->get(route('admin.review', ['journal' => $journal->id, 'proposal_id' => $proposal->id]))
            ->assertOk()->assertSee('Usulan #'.$proposal->id);
        $this->assertDatabaseCount('journal_observations', 0);

        $this->post(route('admin.observation.publish', $journal), [
            'proposal_id' => $proposal->id, 'kind' => 'fee', 'label' => 'Biaya publikasi',
            'value' => '500000 IDR', 'source_url' => 'https://example.org/apc',
            'checked_at' => today()->format('Y-m-d'), 'verification' => 'publisher_claim',
            'review_note' => 'Halaman biaya diperiksa admin dan angka dicocokkan.', 'confirmed' => '1',
        ])->assertRedirect();
        $this->assertDatabaseCount('journal_observations', 1);
        $this->assertDatabaseHas('m18_proposals', ['id' => $proposal->id, 'status' => 'applied',
            'applied_observation_id' => 1]);
        $this->assertDatabaseHas('m18_proposal_events', ['proposal_id' => $proposal->id,
            'from_status' => 'reviewed', 'to_status' => 'applied']);
        $this->actingAs($user)->get(route('proposals.mine'))->assertSee('Diterapkan');
    }
}
