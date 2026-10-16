<?php

namespace Tests\Feature;

use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function journal(int $n, array $data = []): Journal
    {
        return Journal::create(array_merge(['name' => "Journal $n", 'publisher' => 'Penerbit A', 'issn' => "0000-000$n", 'subject' => 'Pendidikan sains', 'official_url' => 'https://example.org/journal', 'scope_source_url' => 'https://example.org/scope', 'scope_summary' => 'Scope pendidikan', 'checked_at' => '2026-01-01', 'status' => 'published'], $data));
    }

    public function test_filters_combine_and_empty_result(): void
    {
        $this->journal(1);
        $this->journal(2, ['subject' => 'Pendidikan bahasa']);
        $this->journal(3, ['publisher' => 'Penerbit B']);
        $this->get('/katalog-jurnal?subject=Pendidikan+sains&publisher=Penerbit+A')->assertOk()->assertSee('Journal 1')->assertDontSee('Journal 2')->assertDontSee('Journal 3');
        $this->get('/katalog-jurnal?subject=Tidak+ada')->assertOk()->assertSee('Belum ada jurnal');
        $this->get('/katalog-jurnal?information=invalid&availability=known')->assertSessionHasErrors('information');
        $this->get('/katalog-jurnal?information=fee')->assertSessionHasErrors('availability');
    }

    public function test_information_availability_never_treats_unknown_as_free(): void
    {
        $a = $this->journal(1);
        $this->journal(2);
        $a->observations()->create(['kind' => 'fee', 'label' => 'APC', 'value' => 'USD 100', 'source_url' => 'https://example.org/apc', 'checked_at' => '2026-01-01', 'verification' => 'publisher_claim']);
        $this->get('/katalog-jurnal?information=fee&availability=known')->assertSee('Journal 1')->assertDontSee('Journal 2');
        $this->get('/katalog-jurnal?information=fee&availability=unknown')->assertDontSee('Journal 1')->assertSee('Journal 2');
        $this->get('/bandingkan?ids[]=1&ids[]=2')->assertOk()->assertSee('USD 100')->assertSee('Belum diketahui')->assertSee('belum diverifikasi independen')->assertSee('https://example.org/apc')->assertSee('01/01/2026');
    }

    public function test_three_journals_in_requested_order_and_fourth_rejected(): void
    {
        foreach (range(1, 4) as $n) {
            $this->journal($n);
        }
        $this->get('/bandingkan?ids[]=3&ids[]=1&ids[]=2')->assertOk()->assertSeeInOrder(['Journal 3', 'Journal 1', 'Journal 2']);
        $this->get('/bandingkan?ids[]=1&ids[]=2&ids[]=3&ids[]=4')->assertSessionHasErrors('ids');
        $this->get('/katalog-jurnal?ids[]=1&ids[]=2&ids[]=3&ids[]=4')->assertSessionHasErrors('ids');
        $this->get('/bandingkan?ids[]=1&ids[]=1')->assertSessionHasErrors('ids.0');
        $this->get('/bandingkan')->assertSessionHasErrors('ids');
        $this->get('/bandingkan?ids=1')->assertSessionHasErrors('ids');
    }

    public function test_draft_archive_and_missing_ids_cannot_leak_into_compare(): void
    {
        $this->journal(1, ['status' => 'draft']);
        $this->journal(2, ['status' => 'archived']);
        foreach ([1, 2, 999] as $id) {
            $this->get('/bandingkan?ids[]='.$id)->assertNotFound();
            $this->get('/katalog-jurnal?ids[]='.$id)->assertNotFound();
        }
    }

    public function test_selection_survives_filters_and_pagination_and_can_be_removed(): void
    {
        foreach (range(1, 14) as $n) {
            $this->journal($n);
        }
        $this->get('/katalog-jurnal?ids[]=1&subject=Pendidikan+sains&page=2')->assertOk()->assertSee('1/3 dipilih')->assertSee('name="ids[]" value="1"', false)->assertSee('ids%5B0%5D=1')->assertSee('Hapus')->assertSee('Lihat perbandingan');
        $this->get('/katalog-jurnal?ids[]=1&ids[]=2&ids[]=3')->assertSee('Maksimal tiga jurnal')->assertDontSee('Pilih untuk dibandingkan');
    }

    public function test_comparison_escapes_catalog_html(): void
    {
        $this->journal(1, ['name' => '<script>alert(1)</script>']);
        $this->get('/bandingkan?ids[]=1')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
