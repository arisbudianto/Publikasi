<?php

namespace Tests\Feature;

use App\Models\JournalSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrawlEvidenceBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_scope_quote_is_previewed_then_synced_to_source_without_claiming_accreditation(): void
    {
        $source = JournalSource::create(['source_id' => 44, 'name' => 'Jurnal Uji',
            'education' => true, 'official_url' => 'https://journal.example.org/about',
            'source_url' => 'https://sinta.example.org/journal/44', 'captured_at' => '2025-03-20',
            'raw_identifiers' => [], 'article_titles' => [], 'dataset_sha256' => str_repeat('a', 64)]);
        $check = DB::table('sinta_page_checks')->insertGetId(['journal_source_id' => $source->id,
            'url' => 'https://journal.example.org/scope', 'status' => 'changed', 'created_at' => now()]);
        $quote = 'This journal welcomes empirical articles in business, management, economics, and accounting.';
        $candidate = DB::table('sinta_page_candidates')->insertGetId(['journal_source_id' => $source->id,
            'check_id' => $check, 'quote' => $quote, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now()]);
        $duplicate = DB::table('sinta_page_candidates')->insertGetId(['journal_source_id' => $source->id,
            'check_id' => $check, 'quote' => 'An older paragraph about management and accounting research.',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('publikasi:sync-crawl-evidence')->assertExitCode(0);
        $this->assertNull($source->fresh()->scope_excerpt);
        $this->artisan('publikasi:sync-crawl-evidence --apply')->assertExitCode(0);
        $this->assertSame($quote, $source->fresh()->scope_excerpt);
        $this->assertDatabaseHas('sinta_page_candidates', ['id' => $candidate, 'status' => 'synced']);
        $this->assertDatabaseHas('sinta_page_candidates', ['id' => $duplicate, 'status' => 'superseded']);
        $this->assertDatabaseHas('sinta_page_checks', ['id' => $check, 'synced' => true]);
        $this->assertDatabaseCount('journals', 0);
        $this->get(route('sources.show', $source))->assertOk()->assertSee($quote);
        $this->artisan('publikasi:sync-crawl-evidence --apply')->assertExitCode(0);
        $this->assertSame($quote, $source->fresh()->scope_excerpt);
    }

    public function test_backfill_is_scheduled_after_daily_crawlers(): void
    {
        $event = collect(\Illuminate\Support\Facades\Schedule::events())
            ->first(fn ($e) => str_contains($e->command, 'publikasi:sync-crawl-evidence --limit=50 --apply'));
        $this->assertNotNull($event);
        $this->assertSame('48 4 * * *', $event->expression);
    }
}
