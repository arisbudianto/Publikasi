<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalDiscontinuedSource;
use App\Models\User;
use App\Services\JournalIntegrityEvaluator;
use App\Services\ScopusDiscontinuedDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class M12JournalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function journal(array $overrides = []): Journal
    {
        return Journal::create(array_merge([
            'name' => 'Jurnal Integritas Uji', 'publisher' => 'Penerbit Uji', 'issn' => '2088-2866',
            'official_url' => 'https://example.org/journal', 'subject' => 'Pendidikan', 'scope_summary' => 'Scope uji M12.',
            'scope_source_url' => 'https://example.org/scope', 'checked_at' => today(), 'status' => 'published',
        ], $overrides));
    }

    private function safeAnswers(): array
    {
        $answers = [];
        foreach (app(JournalIntegrityEvaluator::class)->criteria() as $key => $criterion) {
            $answers[$key] = $criterion['risk'] === 'yes' ? 'no' : 'yes';
        }

        return $answers;
    }

    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'm12');
        $handle = fopen($path, 'wb');
        fputcsv($handle, ScopusDiscontinuedDataset::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), ',', '"', '');
        }
        fclose($handle);

        return $path;
    }

    public function test_evaluator_is_deterministic_and_unknown_is_never_safe(): void
    {
        $evaluator = app(JournalIntegrityEvaluator::class);
        $safe = $evaluator->evaluate($this->safeAnswers());
        $this->assertSame(0, $safe['score']);
        $this->assertSame('Tidak ditemukan masalah pada kriteria yang dijawab', $safe['outcome']);
        $answers = $this->safeAnswers();
        $answers['editorial_board'] = 'unknown';
        $unknown = $evaluator->evaluate($answers);
        $this->assertSame(1, $unknown['unknown_count']);
        $this->assertSame('Perlu diperiksa lebih lanjut', $unknown['outcome']);
        $this->assertSame($unknown, $evaluator->evaluate($answers));
        $answers['rapid_acceptance'] = 'yes';
        $answers['mass_solicitation'] = 'yes';
        $flagged = $evaluator->evaluate($answers);
        $this->assertSame(6, $flagged['score']);
        $this->assertSame('Perlu diperiksa lebih lanjut', $flagged['outcome']);
        $answers['editorial_board'] = 'no';
        $this->assertSame('Banyak tanda yang perlu ditinjau', $evaluator->evaluate($answers)['outcome']);
    }

    public function test_guest_submission_is_not_saved_and_response_is_private(): void
    {
        $this->journal();
        $response = $this->post('/periksa/jurnal', ['lookup' => '2088-2866', 'answers' => $this->safeAnswers()]);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Jurnal katalog yang cocok');
        $this->assertDatabaseCount('journal_integrity_assessments', 0);
        $this->assertNull(session('_old_input'));
    }

    public function test_lookup_and_answers_are_not_flashed_when_validation_fails(): void
    {
        $this->post('/periksa/jurnal', ['lookup' => 'RAHASIA UJI'])->assertUnprocessable()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull(session('_old_input'));
    }

    public function test_logged_in_user_saves_only_after_opt_in_and_can_delete_only_own_record(): void
    {
        $journal = $this->journal();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $answers = $this->safeAnswers();

        $this->actingAs($owner)->post('/periksa/jurnal', ['lookup' => '2088-2866', 'answers' => $answers])->assertOk();
        $this->assertDatabaseCount('journal_integrity_assessments', 0);
        $this->actingAs($owner)->post('/periksa/jurnal', ['lookup' => '2088-2866', 'answers' => $answers, 'save_assessment' => '1'])->assertOk()->assertSee('Teks pencarian tidak disimpan');
        $this->assertDatabaseHas('journal_integrity_assessments', ['user_id' => $owner->id, 'journal_id' => $journal->id, 'criteria_version' => JournalIntegrityEvaluator::VERSION]);
        $assessment = $owner->integrityAssessments()->firstOrFail();
        $this->actingAs($other)->delete('/akun/integritas/'.$assessment->id)->assertNotFound();
        $this->actingAs($owner)->delete('/akun/integritas/'.$assessment->id)->assertRedirect();
        $this->assertDatabaseCount('journal_integrity_assessments', 0);
    }

    public function test_official_discontinued_csv_preview_and_import_are_issn_based_and_versioned(): void
    {
        $journal = $this->journal();
        $dataset = app(ScopusDiscontinuedDataset::class);
        $path = $this->csv([['Sample Scopus Journal', '2088-2866', today()->subYear()->toDateString(), '2019', '2024', 'Coverage discontinued']]);
        try {
            $preview = $dataset->preview($path, today()->toDateString());
            $this->assertCount(1, $preview['rows']);
            $this->assertSame([], $preview['errors']);
            $this->assertSame(hash_file('sha256', $path), $preview['sha256']);
            $result = $dataset->import($preview['rows'], today()->toDateString());
            $this->assertSame(1, $result['current']);
            $this->get('/periksa/discontinued?q=2088-2866')->assertOk()->assertSee('Sample Scopus Journal')->assertSee('Coverage discontinued');
            $this->get('/periksa/jurnal')->assertOk();
            $this->get('/jurnal/'.$journal->id)->assertOk()->assertSee('Scopus mencatat sumber ini sebagai discontinued');
        } finally {
            unlink($path);
        }
    }

    public function test_invalid_or_duplicate_source_rows_are_rejected_before_database_write(): void
    {
        $dataset = app(ScopusDiscontinuedDataset::class);
        $path = $this->csv([
            ['First title', '2088-2866', '', '2020', '2024', ''],
            ['Duplicate ISSN', '2088-2866', '', '2020', '2024', ''],
            ['Bad checksum', '1234-5678', '', '2020', '2024', ''],
        ]);
        try {
            $preview = $dataset->preview($path, today()->toDateString());
            $this->assertNotEmpty($preview['errors']);
            $this->assertDatabaseCount('journal_discontinued_sources', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_newer_snapshot_replaces_current_marker_without_removing_history(): void
    {
        $dataset = app(ScopusDiscontinuedDataset::class);
        $firstDate = today()->subDays(5)->toDateString();
        foreach ([[$firstDate, 'Old snapshot'], [today()->toDateString(), 'New snapshot']] as [$date, $title]) {
            $path = $this->csv([[$title, '2088-2866', '', '2019', '2024', '']]);
            try {
                $preview = $dataset->preview($path, $date);
                $dataset->import($preview['rows'], $date);
            } finally {
                unlink($path);
            }
        }
        $this->assertDatabaseCount('journal_discontinued_sources', 2);
        $this->assertDatabaseHas('journal_discontinued_sources', ['source_title' => 'Old snapshot', 'is_current' => 0]);
        $this->assertDatabaseHas('journal_discontinued_sources', ['source_title' => 'New snapshot', 'is_current' => 1]);
        $this->get('/periksa/discontinued?q=2088-2866')->assertSee('New snapshot')->assertDontSee('Old snapshot');
    }

    public function test_basic_matching_results_warn_when_a_catalog_issn_is_discontinued(): void
    {
        $journal = $this->journal(['scope_summary' => 'Pembelajaran vokasi dan pelatihan digital.']);
        JournalDiscontinuedSource::create([
            'source_title' => 'Jurnal Integritas Uji', 'issn' => $journal->issn,
            'coverage_start_year' => 2018, 'coverage_end_year' => 2024,
            'source_url' => ScopusDiscontinuedDataset::SOURCE_URL, 'source_as_of' => today(),
            'imported_at' => now(), 'is_current' => true,
        ]);
        $this->post('/cocokkan', [
            'title' => 'Vocational learning outcomes',
            'abstract' => str_repeat('This study examines vocational learning and digital training outcomes. ', 2),
        ])->assertOk()->assertSee('Scopus mencatat sumber ini sebagai discontinued')->assertSee('tidak menyatakan artikel sebelum akhir cakupan tidak terindeks');
    }

    public function test_discontinued_import_rejects_invalid_or_older_source_date(): void
    {
        $dataset = app(ScopusDiscontinuedDataset::class);
        $path = $this->csv([['First title', '2088-2866', '', '2020', '2024', '']]);
        try {
            $this->expectException(ValidationException::class);
            $dataset->preview($path, '2026-02-30');
        } finally {
            unlink($path);
        }
    }
}
