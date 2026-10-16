<?php

namespace Tests\Feature;

use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class M11DirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function journal(int $number, array $overrides = []): Journal
    {
        return Journal::create(array_merge([
            'name' => "Jurnal M11 $number", 'publisher' => 'Penerbit uji', 'issn' => sprintf('2040-%04d', $number),
            'official_url' => 'https://example.org/journal', 'subject' => 'Pendidikan', 'scope_summary' => 'Scope uji direktori.',
            'scope_source_url' => 'https://example.org/scope', 'checked_at' => today()->subDays(10), 'status' => 'published',
        ], $overrides));
    }

    private function observation(Journal $journal, array $overrides = []): void
    {
        $journal->observations()->create(array_merge([
            'kind' => 'fee', 'label' => 'Biaya APC', 'value' => 'Rp0', 'verification' => 'verified',
            'source_url' => 'https://example.org/fees', 'checked_at' => today()->subDays(10),
            'fee_route' => 'apc_oa', 'amount_minor' => 0, 'currency' => 'IDR',
        ], $overrides));
    }

    public function test_no_apc_only_shows_current_verified_zero_and_separates_unknown(): void
    {
        $free = $this->journal(1);
        $this->observation($free);
        $paid = $this->journal(2);
        $this->observation($paid, ['value' => 'USD 100', 'amount_minor' => 10000, 'currency' => 'USD']);
        $claim = $this->journal(3);
        $this->observation($claim, ['verification' => 'publisher_claim']);
        $stale = $this->journal(4);
        $this->observation($stale, ['effective_to' => today()->subDay()]);

        $this->get('/tanpa-apc')->assertOk()->assertSee('Jurnal M11 1')->assertDontSee('Jurnal M11 2');
        $this->get('/tanpa-apc')->assertSee('Biaya APC belum diketahui')->assertSee('Jurnal M11 3')->assertSee('Jurnal M11 4')->assertDontSee('Jurnal M11 2');
    }

    public function test_apc_maximum_requires_currency_and_zero_is_a_real_limit(): void
    {
        $zero = $this->journal(1);
        $this->observation($zero);
        $paid = $this->journal(2);
        $this->observation($paid, ['amount_minor' => 1, 'value' => 'IDR 1']);

        $this->get('/katalog-jurnal?apc_max_minor=0')->assertSessionHasErrors('apc_currency');
        $this->get('/katalog-jurnal?apc_max_minor=0&apc_currency=IDR')->assertOk()->assertSee('Jurnal M11 1')->assertDontSee('Jurnal M11 2');
        $this->get('/katalog-jurnal?sort=cost')->assertSessionHasErrors('apc_currency');
    }

    public function test_rank_scheme_is_not_mixed_and_verified_filter_is_explicit(): void
    {
        $journal = $this->journal(1);
        $journal->observations()->create(['kind' => 'index', 'label' => 'SINTA', 'value' => '2', 'verification' => 'verified', 'source_url' => 'https://example.org/sinta', 'checked_at' => today(), 'metric_scheme' => 'SINTA-2026', 'rank_value' => 2, 'period' => '2026']);
        $old = $this->journal(2);
        $old->observations()->create(['kind' => 'index', 'label' => 'SINTA', 'value' => '2', 'verification' => 'verified', 'source_url' => 'https://example.org/old', 'checked_at' => today(), 'metric_scheme' => 'SINTA-lama', 'rank_value' => 2, 'period' => '2020']);

        $this->get('/katalog-jurnal?rank_scheme=SINTA-2026&rank_value=2')->assertSee('Jurnal M11 1')->assertDontSee('Jurnal M11 2');
        $this->get('/katalog-jurnal?sort=rank')->assertSessionHasErrors('rank_scheme');
    }

    public function test_filters_persist_when_returning_from_comparison(): void
    {
        $this->journal(1);
        $this->get('/bandingkan?ids[]=1&subject=Pendidikan&sort=name')->assertOk()->assertSee('/katalog-jurnal?subject=Pendidikan', false)->assertSee('ids%5B0%5D=1');
    }

    public function test_frequency_range_is_validated_and_catalog_pages_are_public(): void
    {
        $this->journal(1);
        $this->get('/katalog-jurnal?frequency_min=4&frequency_max=2')->assertSessionHasErrors('frequency_max');
        $this->get('/katalog-jurnal')->assertOk();
        $this->get('/tanpa-apc')->assertOk();
    }
}
