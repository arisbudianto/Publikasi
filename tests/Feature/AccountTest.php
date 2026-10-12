<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\MatchHistory;
use App\Models\PersonalShortlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function journal(): Journal
    {
        return Journal::create(['name' => 'Jurnal Vokasi', 'publisher' => 'Uji', 'issn' => '2088-2866', 'subject' => 'Vokasi', 'scope_summary' => 'Kurikulum pelatihan vokasi', 'scope_source_url' => 'https://example.org/scope', 'official_url' => 'https://example.org', 'checked_at' => '2026-01-01', 'status' => 'published']);
    }

    private function matchInput(bool $save): array
    {
        return ['title' => 'Kurikulum pendidikan vokasi', 'abstract' => str_repeat('Kurikulum pelatihan vokasi untuk peserta didik. ', 3), 'context' => 'RAHASIA_CONTEXT', 'save_history' => $save ? '1' : '0'];
    }

    public function test_registration_never_grants_admin_or_verifies_email(): void
    {
        $this->post('/akun/daftar', ['name' => 'Nama Uji', 'email' => 'USER@EXAMPLE.ORG', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'is_admin' => true, 'email_verified_at' => '2026-01-01'])->assertRedirect('/akun');
        $u = User::first();
        $this->assertFalse($u->is_admin);
        $this->assertNull($u->email_verified_at);
        $this->assertSame('user@example.org', $u->email);
        $this->assertTrue(Hash::check('StrongPassword123', $u->password));
        $this->assertAuthenticatedAs($u);
        $this->post('/admin/jurnal', [])->assertForbidden();
    }

    public function test_login_logout_and_invalid_input_no_password_flash(): void
    {
        User::factory()->create(['email' => 'user@example.org', 'password' => 'StrongPassword123']);
        $this->post('/akun/login', ['email' => 'user@example.org', 'password' => 'WrongPassword123'])->assertStatus(422);
        $this->assertGuest();
        $this->assertNull(session('_old_input'));
        $this->post('/akun/login', ['email' => 'USER@EXAMPLE.ORG', 'password' => 'StrongPassword123'])->assertRedirect('/akun');
        $this->assertAuthenticated();
        $this->post('/akun/keluar')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_shortlist_owned_idempotent_and_private_lifecycle(): void
    {
        $u = User::factory()->create();
        $j = $this->journal();
        $this->post('/akun/shortlist/'.$j->id)->assertRedirect('/akun/login');
        $this->actingAs($u)->post('/akun/shortlist/'.$j->id)->assertRedirect();
        $this->post('/akun/shortlist/'.$j->id)->assertRedirect();
        $this->assertDatabaseCount('personal_shortlists', 1);
        $j->update(['status' => 'archived']);
        $this->post('/akun/shortlist/'.$j->id)->assertNotFound();
        $this->get('/akun')->assertOk()->assertSee('sudah tidak dipublikasikan')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_another_user_and_admin_cannot_read_or_delete_private_lists(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $j = $this->journal();
        $s = PersonalShortlist::create(['user_id' => $owner->id, 'journal_id' => $j->id]);
        $h = MatchHistory::create(['user_id' => $owner->id, 'title' => 'PRIVATE_HISTORY_TITLE', 'mode' => 'keyword', 'candidates' => []]);
        foreach ([$other, $admin] as $u) {
            $this->actingAs($u)->get('/akun')->assertOk()->assertDontSee('PRIVATE_HISTORY_TITLE')->assertDontSee('Jurnal Vokasi');
            $this->delete('/akun/shortlist/'.$s->id)->assertNotFound();
            $this->delete('/akun/riwayat/'.$h->id)->assertNotFound();
        }
        $this->assertDatabaseCount('personal_shortlists', 1);
        $this->assertDatabaseCount('match_histories', 1);
        $this->actingAs($owner)->delete('/akun/shortlist/'.$s->id)->assertRedirect();
        $this->delete('/akun/riwayat/'.$h->id)->assertRedirect();
        $this->assertDatabaseCount('personal_shortlists', 0);
        $this->assertDatabaseCount('match_histories', 0);
    }

    public function test_history_opt_in_never_keeps_abstract_context_or_guest_input(): void
    {
        $j = $this->journal();
        $this->post('/cocokkan', $this->matchInput(true))->assertOk();
        $this->assertDatabaseCount('match_histories', 0);
        $u = User::factory()->create();
        $this->actingAs($u)->post('/cocokkan', $this->matchInput(false))->assertOk();
        $this->assertDatabaseCount('match_histories', 0);
        $this->post('/cocokkan', $this->matchInput(true))->assertOk();
        $this->assertDatabaseCount('match_histories', 1);
        $h = MatchHistory::first();
        $this->assertSame($u->id, $h->user_id);
        $this->assertStringNotContainsString('RAHASIA_CONTEXT', $h->toJson());
        $this->assertStringNotContainsString($this->matchInput(true)['abstract'], $h->toJson());
        $this->assertSame($j->id, $h->candidates[0]['id']);
        $this->get('/akun')->assertSee('Kurikulum pendidikan vokasi');
    }

    public function test_profile_and_password_require_current_password(): void
    {
        $u = User::factory()->create(['password' => 'StrongPassword123']);
        $this->actingAs($u)->put('/akun/profil', ['name' => 'New Name', 'is_admin' => true])->assertRedirect();
        $this->assertFalse($u->fresh()->is_admin);
        $this->put('/akun/password', ['current_password' => 'wrong', 'password' => 'AnotherStrongPass123', 'password_confirmation' => 'AnotherStrongPass123'])->assertStatus(422);
        $this->assertTrue(Hash::check('StrongPassword123', $u->fresh()->password));
        $this->put('/akun/password', ['current_password' => 'StrongPassword123', 'password' => 'AnotherStrongPass123', 'password_confirmation' => 'AnotherStrongPass123'])->assertRedirect();
        $this->assertTrue(Hash::check('AnotherStrongPass123', $u->fresh()->password));
    }

    public function test_registration_password_confirmation_and_short_password_rejected(): void
    {
        $this->post('/akun/daftar', ['name' => 'Test', 'email' => 'test@example.org', 'password' => 'short', 'password_confirmation' => 'wrong'])->assertStatus(422);
        $this->assertDatabaseCount('users',0);
        $this->get('/akun')->assertRedirect('/akun/login');
    }

    public function test_old_session_is_invalidated_after_password_changed(): void
    {
        $u=User::factory()->create(['password'=>'StrongPassword123']);
        $old=$u->password;
        $u->update(['password'=>'AnotherStrongPass123']);
        $this->actingAs($u)->withSession(['password_hash_web'=>$old])->get('/akun')->assertRedirect('/akun/login');
        $this->assertGuest();
    }
    public function test_account_login_rate_limit_and_existing_admin_is_not_overwritten(): void
    {
        $u=User::factory()->create(['email'=>'admin@example.org']);$u->is_admin=true;$u->save();
        $this->post('/akun/daftar',['name'=>'Overwrite','email'=>'ADMIN@EXAMPLE.ORG','password'=>'StrongPassword123','password_confirmation'=>'StrongPassword123'])->assertStatus(422);
        $this->assertTrue($u->fresh()->is_admin);$this->assertDatabaseCount('users',1);
        for($i=0;$i<5;$i++){$this->post('/akun/login',['email'=>'unknown@example.org','password'=>'WrongPass123'])->assertStatus(422);}
        $this->post('/akun/login',['email'=>'unknown@example.org','password'=>'WrongPass123'])->assertStatus(429);
    }
    public function test_ai_history_is_opt_in_and_keeps_only_public_candidate_snapshot(): void
    {
        $u=User::factory()->create();$j=$this->journal();
        config(['semantic.enabled'=>true,'semantic.key'=>'TEST_ONLY']);
        $this->mock(\App\Services\SemanticMatcher::class, fn($m)=>$m->shouldReceive('match')->once()->andReturn([['journal'=>$j,'similarity'=>0.8,'evidence'=>['articles'=>[],'status'=>'empty']]]));
        $data=$this->matchInput(true);$data['consent']='1';
        $this->actingAs($u)->post('/cocokkan-ai',$data)->assertOk();
        $h=MatchHistory::first();$this->assertSame('semantic',$h->mode);$this->assertSame($u->id,$h->user_id);$this->assertStringNotContainsString('RAHASIA_CONTEXT',$h->toJson());
    }
}
