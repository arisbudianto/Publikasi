<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\User;
use App\Services\CuratedJournalDiscovery;
use App\Services\SintaAccreditationDataset;
use App\Services\SintaDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CuratedJournalDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_snapshots_produce_reviewable_leads(): void
    {
        app(SintaDataset::class)->import();
        app(SintaAccreditationDataset::class)->import();
        $result = app(CuratedJournalDiscovery::class)->tick();
        $this->assertGreaterThan(0, $result['created']);
        $this->assertLessThanOrEqual(20, $result['created']);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_discovery_queues_linked_decisions_without_publishing_and_admin_can_review(): void
    {
        $source = DB::table('journal_sources')->insertGetId([
            'source_id' => 123, 'name' => 'Jurnal Pendidikan Teruji', 'e_issn' => '23556544',
            'official_url' => 'https://journal.example.org', 'source_url' => 'https://sinta.kemdiktisaintek.go.id/journals/profile/123',
            'captured_at' => '2025-03-20', 'education' => true, 'raw_identifiers' => '{}',
            'article_titles' => '[]', 'dataset_sha256' => str_repeat('a', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $accreditation = DB::table('journal_accreditations')->insertGetId([
            'item_key' => 'test:123', 'rank' => 2, 'journal_name' => 'Jurnal Pendidikan Teruji',
            'e_issn_raw' => '23556544', 'e_issn_checksum_valid' => true,
            'decision_type' => 'new', 'decision_text' => 'Terakreditasi peringkat 2',
            'source_page' => 12, 'decree_number' => '355/2026', 'decree_date' => '2026-07-24',
            'period' => '2025-3', 'source_pdf_sha256' => str_repeat('b', 64),
            'journal_source_id' => $source, 'match_status' => 'matched',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(CuratedJournalDiscovery::class)->tick();
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, app(CuratedJournalDiscovery::class)->tick()['created']);
        $this->assertDatabaseHas('curation_leads', ['journal_source_id' => $source, 'accreditation_id' => $accreditation, 'status' => 'pending']);
        $this->assertDatabaseCount('journals', 0);

        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $lead = DB::table('curation_leads')->value('id');
        $this->actingAs($user)->get(route('admin.agent.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.agent.index'))->assertOk()
            ->assertSee('Jurnal Pendidikan Teruji')->assertSee(route('admin.sources.prepare', $source));
        $this->post(route('admin.agent.lead.dismiss', $lead), ['note' => 'Sumber tidak lagi dapat diverifikasi.'])->assertRedirect();
        $this->assertDatabaseHas('curation_leads', ['id' => $lead, 'status' => 'dismissed']);
        $this->assertSame(0, app(CuratedJournalDiscovery::class)->tick()['created']);
        DB::table('curation_leads')->delete();
        Journal::create(['name' => 'Jurnal Pendidikan Teruji', 'publisher' => 'Penerbit', 'issn' => '23556544',
            'official_url' => 'https://journal.example.org', 'scope_source_url' => 'https://journal.example.org/about',
            'scope_summary' => 'Pendidikan', 'subject' => 'Pendidikan', 'checked_at' => today(), 'status' => 'published']);
        $this->assertSame(0, app(CuratedJournalDiscovery::class)->tick()['created']);
    }
}
