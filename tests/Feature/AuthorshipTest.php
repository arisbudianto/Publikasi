<?php

namespace Tests\Feature;

use App\Services\AuthorshipEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_version_sources_and_privacy_notice(): void
    {
        $this->assertCount(14, app(AuthorshipEvaluator::class)->roles());
        $this->get('/periksa/authorship')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, noarchive')
            ->assertSee(AuthorshipEvaluator::VERSION)
            ->assertSee('Keputusan akhir mengikuti kebijakan jurnal dan institusi')
            ->assertSee('CRediT sendiri tidak menentukan authorship')
            ->assertSee('Writing – review &amp; editing · Peninjauan dan penyuntingan', false)
            ->assertSee('tidak menyimpan nama, jawaban, atau peran');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_contributor_meeting_all_four_criteria_is_listed_as_author_candidate(): void
    {
        $roles = array_keys(app(AuthorshipEvaluator::class)->roles());
        $response = $this->post('/periksa/authorship', ['people' => [[
            'name' => 'Ayu Peneliti', 'criteria' => ['c1', 'c2', 'c3', 'c4'], 'roles' => [$roles[0], 'writing_original_draft'],
        ]]]);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Ayu Peneliti')->assertSee('Memenuhi empat syarat ICMJE')
            ->assertSee('Conceptualization · Konseptualisasi')->assertSee('Penulisan draf awal')
            ->assertSee('Ayu Peneliti:');
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(session('_old_input'));
    }

    public function test_missing_criteria_are_listed_and_acknowledgement_is_suggested(): void
    {
        $this->post('/periksa/authorship', ['people' => [[
            'name' => 'Bima Kontributor', 'criteria' => ['c1', 'c3'], 'roles' => ['investigation'],
        ]]])->assertOk()->assertSee('Bima Kontributor')->assertSee('Belum memenuhi semua syarat')
            ->assertSee('C2: Menyusun naskah')->assertSee('C4: Bersedia bertanggung jawab')
            ->assertSee('pertimbangkan ucapan terima kasih dengan izin tertulis');
    }

    public function test_funding_or_supervision_only_never_autoqualifies_as_author(): void
    {
        $this->post('/periksa/authorship', ['people' => [[
            'name' => 'Citra Penyandang Dana', 'criteria' => ['c1', 'c2', 'c3', 'c4'], 'roles' => ['funding_acquisition', 'supervision'],
        ]]])->assertOk()->assertSee('Tinjau kembali syarat pertama')
            ->assertSee('tidak memenuhi kontribusi substantif pada syarat pertama ICMJE')
            ->assertDontSee('Usulan urutan sementara:</strong> Citra Penyandang Dana');
    }

    public function test_more_than_thirty_people_are_rejected_without_flashing_input(): void
    {
        $people = [];
        for ($i = 0; $i < 31; $i++) {
            $people[] = ['name' => 'Person '.$i, 'criteria' => [], 'roles' => []];
        }
        $this->post('/periksa/authorship', ['people' => $people])->assertUnprocessable()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Periksa kembali isian.');
        $this->assertNull(session('_old_input'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_credit_role_is_rejected_without_flashing_input(): void
    {
        $this->post('/periksa/authorship', ['people' => [[
            'name' => 'Danu Kontributor', 'criteria' => [], 'roles' => ['not-a-credit-role'],
        ]]])->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull(session('_old_input'));
    }

    public function test_contributor_name_is_html_escaped_in_result(): void
    {
        $response = $this->post('/periksa/authorship', ['people' => [[
            'name' => '<script>alert(1)</script>', 'criteria' => ['c1', 'c2', 'c3', 'c4'], 'roles' => [],
        ]]]);
        $response->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_evaluator_orders_author_candidates_by_role_count_without_weighting_roles(): void
    {
        $result = app(AuthorshipEvaluator::class)->evaluate([
            ['name' => 'First', 'criteria' => ['c1', 'c2', 'c3', 'c4'], 'roles' => ['conceptualization']],
            ['name' => 'Second', 'criteria' => ['c1', 'c2', 'c3', 'c4'], 'roles' => ['investigation', 'writing_original_draft']],
            ['name' => 'Third', 'criteria' => ['c1', 'c2', 'c3', 'c4'], 'roles' => ['funding_acquisition']],
        ]);
        $this->assertSame(['Second', 'First'], $result['author_order']);
        $this->assertSame('review', $result['people'][2]['status']);
    }
}
