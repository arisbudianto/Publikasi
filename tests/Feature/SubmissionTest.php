<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function start(): Submission
    {
        $j = Journal::create(['name' => 'Jurnal Tracking', 'publisher' => 'Uji', 'issn' => '2088-2866', 'subject' => 'Vokasi', 'scope_summary' => 'Scope', 'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org', 'checked_at' => today(), 'status' => 'published']);
        $this->actingAs(User::factory()->create())->post('/akun/submission', ['journal_id' => $j->id, 'title' => 'PRIVATE_MANUSCRIPT', 'note' => 'OWNER_NOTE'])->assertRedirect();

        return Submission::first();
    }

    private function update(Submission $s, array $extra = []): array
    {
        return $extra + ['title' => $s->title, 'status' => 'submitted', 'version' => $s->version, 'occurred_at' => now('Asia/Jakarta')->subDays(2)->format('Y-m-d')];
    }

    private function row(array $extra = []): array
    {
        return $extra + ['round' => 1, 'reviewer' => 'Reviewer 1', 'comment' => 'PRIVATE_COMMENT', 'status' => 'pending'];
    }

    public function test_creation_owner_views_and_archived_journal_continuity(): void
    {
        $s = $this->start();
        $this->get('/akun/submission')->assertOk()->assertSee('PRIVATE_MANUSCRIPT')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/akun/submission/'.$s->id)->assertOk()->assertSee('OWNER_NOTE');
        $s->journal->update(['status' => 'archived']);
        $this->get('/akun/submission/'.$s->id)->assertOk();
        $this->post('/akun/submission', ['journal_id' => $s->journal_id, 'title' => 'Other'])->assertSessionHasErrors('journal_id');
        $this->put('/akun/submission/'.$s->id, $this->update($s))->assertRedirect();
        $this->assertDatabaseCount('submission_events', 1);
    }

    public function test_transition_rules_dates_and_stale_write_protect_timeline(): void
    {
        $s = $this->start();
        $url = '/akun/submission/'.$s->id;
        $this->put($url, $this->update($s, ['status' => 'accepted']))->assertSessionHasErrors('status');
        $this->put($url, $this->update($s, ['occurred_at' => null]))->assertSessionHasErrors('occurred_at');
        $this->put($url, $this->update($s, ['occurred_at' => now('Asia/Jakarta')->addDay()->format('Y-m-d')]))->assertSessionHasErrors('occurred_at');
        $this->put($url, $this->update($s))->assertRedirect();
        $this->put($url, $this->update($s))->assertStatus(409);
        $s->refresh();
        $this->put($url, $this->update($s, ['status' => 'review', 'occurred_at' => now('Asia/Jakarta')->subDays(3)->format('Y-m-d')]))->assertSessionHasErrors('occurred_at');
        $this->put($url, $this->update($s, ['status' => 'accepted']))->assertRedirect();
        $s->refresh();
        $this->put($url, $this->update($s, ['status' => 'review']))->assertSessionHasErrors('status');
        $this->assertDatabaseCount('submission_events', 2);
    }

    public function test_matrix_requires_response_and_location_and_has_stale_protection(): void
    {
        $s = $this->start();
        $url = '/akun/submission/'.$s->id.'/respons';
        $this->post($url, $this->row(['status' => 'addressed']))->assertSessionHasErrors(['response', 'location']);
        $this->post($url, $this->row(['round' => 100]))->assertSessionHasErrors('round');
        $this->post($url, $this->row())->assertRedirect();
        $row = $s->responses()->first();
        $this->put($url.'/'.$row->id, $this->row(['status' => 'addressed', 'response' => 'Revised method', 'location' => 'p4', 'version' => 1]))->assertRedirect();
        $this->put($url.'/'.$row->id, $this->row(['version' => 1]))->assertStatus(409);
        $this->assertSame('addressed', $row->fresh()->status);
        $other = Submission::create(['user_id' => $s->user_id, 'journal_id' => $s->journal_id, 'title' => 'Second']);
        $this->put('/akun/submission/'.$other->id.'/respons/'.$row->id, $this->row(['version' => 2]))->assertNotFound();
    }

    public function test_other_accounts_even_admin_cannot_read_write_or_export(): void
    {
        $s = $this->start();
        $this->post('/akun/submission/'.$s->id.'/respons', $this->row());
        $row = $s->responses()->first();
        $other = User::factory()->create();
        $other->is_admin = true;
        $other->save();
        $this->actingAs($other);
        $this->get('/akun/submission')->assertDontSee('PRIVATE_MANUSCRIPT');
        $this->get('/akun/submission/'.$s->id)->assertNotFound();
        $this->get('/akun/submission/'.$s->id.'/matriks.csv')->assertNotFound();
        $this->put('/akun/submission/'.$s->id, $this->update($s))->assertNotFound();
        $this->post('/akun/submission/'.$s->id.'/respons', $this->row())->assertNotFound();
        $this->put('/akun/submission/'.$s->id.'/respons/'.$row->id, $this->row(['version' => 1]))->assertNotFound();
        $this->assertDatabaseCount('submission_events', 0);
    }

    public function test_csv_neutralizes_spreadsheet_formulas_and_guest_blocked(): void
    {
        $s = $this->start();
        $this->post('/akun/submission/'.$s->id.'/respons', $this->row(['comment' => '=HYPERLINK("bad")', 'reviewer' => ' @SUM(1)']));
        $r = $this->get('/akun/submission/'.$s->id.'/matriks.csv')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $csv = $r->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
        auth()->logout();
        $this->getJson('/akun/submission')->assertUnauthorized();
        $this->postJson('/akun/submission',[])->assertUnauthorized();
    }
}
