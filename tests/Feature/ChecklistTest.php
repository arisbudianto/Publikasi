<?php

namespace Tests\Feature;

use App\Http\Controllers\ChecklistController;
use App\Models\Journal;
use App\Models\SubmissionChecklist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function journal(): Journal
    {
        return Journal::create(['name' => 'Jurnal Checklist', 'publisher' => 'Uji', 'issn' => '2088-2866', 'subject' => 'Vokasi', 'scope_summary' => 'Scope', 'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org', 'checked_at' => today(), 'status' => 'published']);
    }

    private function data(int $version = 0): array
    {
        return ['answers' => array_fill_keys(array_keys(ChecklistController::ITEMS), 'done'), 'note' => 'PRIVATE_NOTE_TEST', 'version' => $version];
    }

    public function test_public_default_and_guest_cannot_save(): void
    {
        $j = $this->journal();
        $this->get('/jurnal/'.$j->id.'/checklist')->assertOk()->assertSee('0/10')->assertSee('bukan verifikasi editor');
        $this->post('/akun/checklist/'.$j->id, $this->data())->assertRedirect('/akun/login');
        $this->assertDatabaseCount('submission_checklists', 0);
    }

    public function test_owner_saves_progress_and_stale_write_rejected(): void
    {
        $j = $this->journal();
        $u = User::factory()->create();
        $this->actingAs($u)->post('/akun/checklist/'.$j->id, $this->data())->assertRedirect();
        $this->get('/jurnal/'.$j->id.'/checklist')->assertSee('10/10')->assertSee('PRIVATE_NOTE_TEST')->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/akun/checklist/'.$j->id, $this->data())->assertStatus(409);
        $this->post('/akun/checklist/'.$j->id, $this->data(1))->assertRedirect();
        $this->assertSame(2, SubmissionChecklist::first()->version);
        $this->assertDatabaseCount('submission_checklists', 1);
        $this->get('/akun')->assertSee('Jurnal Checklist');
    }

    public function test_other_users_and_admin_cannot_read_private_note(): void
    {
        $j = $this->journal();
        $u = User::factory()->create();
        $this->actingAs($u)->post('/akun/checklist/'.$j->id, $this->data());
        $other = User::factory()->create();
        $other->is_admin = true;
        $other->save();
        $this->actingAs($other)->get('/jurnal/'.$j->id.'/checklist')->assertDontSee('PRIVATE_NOTE_TEST')->assertSee('0/10');
        $this->get('/akun')->assertDontSee('Jurnal Checklist');
        $j->update(['status' => 'archived']);
        $this->get('/jurnal/'.$j->id.'/checklist')->assertNotFound();
        $this->actingAs($u)->get('/jurnal/'.$j->id.'/checklist')->assertOk()->assertSee('hanya dapat dibaca');
        $this->post('/akun/checklist/'.$j->id, $this->data(1))->assertStatus(422);
    }

    public function test_validation_unknown_keys_and_required_items_cannot_be_na(): void
    {
        $j = $this->journal();
        $this->actingAs(User::factory()->create());
        $d = $this->data();
        $d['answers']['ethics'] = 'na';
        $this->post('/akun/checklist/'.$j->id, $d)->assertSessionHasErrors('answers.ethics');
        $d = $this->data();
        $d['answers']['extra'] = 'done';
        $this->post('/akun/checklist/'.$j->id, $d)->assertSessionHasErrors('answers');
        $d = $this->data();
        $d['answers']['supplements'] = 'na';
        $this->post('/akun/checklist/'.$j->id, $d)->assertRedirect();
    }
}
