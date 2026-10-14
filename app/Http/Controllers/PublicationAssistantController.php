<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\M18Guide;
use App\Models\ScopusSource;
use App\Services\DinoikiResearchAi;
use App\Services\JournalDirectory;
use Illuminate\Http\Request;
use RuntimeException;

class PublicationAssistantController extends Controller
{
    public function index(Request $request)
    {
        $journal = $request->integer('journal')
            ? Journal::where('status', 'published')->findOrFail($request->integer('journal')) : null;

        return view('assistant.index', ['question' => $journal
            ? 'Apa cakupan '.$journal->name.' ISSN '.$journal->issn.' dan apakah biaya publikasinya diketahui?'
            : '', 'contextJournal' => $journal]);
    }

    public function answer(Request $request, DinoikiResearchAi $ai, JournalDirectory $directory)
    {
        $data = $request->validate(['question' => 'required|string|min:12|max:500']);
        $question = trim($data['question']);
        preg_match('/\b[0-9]{4}-[0-9]{3}[0-9X]\b/i', $question, $issnMatch);
        $issn = strtoupper($issnMatch[0] ?? '');
        $words = preg_split('/[^\pL\pN]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY);
        $common = ['jurnal', 'journal', 'pendidikan', 'apakah', 'bagaimana', 'berapa', 'tentang', 'cakupan', 'scope', 'biaya', 'publikasi', 'dengan', 'untuk', 'dari', 'yang', 'sudah', 'diketahui', 'indonesian', 'indonesia', 'universitas', 'university'];
        $words = array_slice(array_values(array_unique(array_filter($words,
            fn ($word) => mb_strlen($word) >= 4 && ! in_array($word, $common, true) && ! ctype_digit($word)))), 0, 8);
        $sources = [];
        $matchedJournal = null;
        $matchedScopus = null;
        if ($issn !== '' || $words !== []) {
            $journalQuery = Journal::query()->where('status', 'published');
            if ($issn !== '') {
                $journalQuery->where('issn', $issn);
            } else {
                $journalQuery->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $escaped = addcslashes($word, '%_\\');
                    $query->orWhere('name', 'like', '%'.$escaped.'%')
                        ->orWhere('subject', 'like', '%'.$escaped.'%')
                        ->orWhere('scope_summary', 'like', '%'.$escaped.'%');
                }
                });
            }
            $journals = $journalQuery->limit(6)->get();
            if ($journals->count() === 1 && ($issn !== '' || mb_stripos($question, $journals->first()->name) !== false)) {
                $matchedJournal = $journals->first();
            }
            foreach ($journals as $journal) {
                $observations = $directory->currentObservations($journal->observations())
                    ->whereIn('kind', ['index', 'fee', 'time'])->limit(8)->get();
                $sources[] = [
                    'id' => 'journal:'.$journal->id, 'title' => $journal->name,
                    'url' => route('catalog.show', $journal),
                    'excerpt' => mb_substr(implode(' | ', array_filter([
                        'Subjek: '.$journal->subject, 'Cakupan: '.$journal->scope_summary,
                        ...$observations->map(fn ($item) => $item->label.': '.$item->value.' ('.$item->verification.'; diperiksa '.($item->checked_at?->toDateString() ?? 'tidak tercatat').'; sumber '.$item->source_url.')')->all(),
                    ])), 0, 2000),
                ];
            }
            if ($issn !== '') {
                $scopus = ScopusSource::where('is_current', true)
                    ->where('source_type', 'Journal')
                    ->where(fn ($query) => $query->where('issn', str_replace('-', '', $issn))->orWhere('eissn', str_replace('-', '', $issn)))
                    ->limit(6)->get();
                if ($scopus->count() === 1) $matchedScopus = $scopus->first();
                foreach ($scopus as $source) {
                    $sources[] = [
                        'id' => 'scopus:'.$source->id, 'title' => $source->source_title.' (Scopus '.$source->snapshot_month.')',
                        'url' => route('scopus.index', ['q' => $issn]),
                        'excerpt' => 'Source Record ID: '.$source->source_record_id.'; status snapshot: '.$source->status.'; cakupan: '.($source->coverage ?: 'tidak tercatat').'; ditandai discontinued: '.($source->discontinued ? 'ya' : 'tidak').'. Data snapshot, bukan jaminan status hari ini.',
                    ];
                }
            }
            $guides = M18Guide::query()->whereNotNull('published_at')->where('published_at', '<=', now())
                ->when($issn !== '', fn ($query) => $query->whereRaw('1 = 0'))
                ->where(function ($query) use ($words) {
                    foreach ($words as $word) {
                        $escaped = addcslashes($word, '%_\\');
                        $query->orWhere('title', 'like', '%'.$escaped.'%')->orWhere('body', 'like', '%'.$escaped.'%');
                    }
                })->latest('published_at')->limit(4)->get();
            foreach ($guides as $guide) {
                $sources[] = ['id' => 'guide:'.$guide->id, 'title' => $guide->title,
                    'url' => route('guides.show', $guide->slug),
                    'excerpt' => mb_substr($guide->body, 0, 1700).' (ditinjau '.$guide->reviewed_at?->toDateString().')'];
            }
        }
        $sentences = [];
        $notice = null;
        if ($sources === []) {
            $notice = 'Belum ada data katalog atau panduan terbit yang cocok. Coba nama jurnal atau topik yang lebih spesifik.';
        } elseif ($matchedJournal !== null) {
            if (preg_match('/cakupan|scope|bidang/i', $question) && filled($matchedJournal->scope_summary)) {
                $sentences[] = ['text' => 'Menurut katalog, cakupan '.$matchedJournal->name.': '.$matchedJournal->scope_summary,
                    'source_ids' => ['journal:'.$matchedJournal->id]];
            }
            if (preg_match('/biaya|apc|ongkos/i', $question)) {
                $fee = $directory->currentObservations($matchedJournal->observations())
                    ->where('kind', 'fee')->whereNotNull('value')->where('value', '!=', '')->latest('checked_at')->first();
                $sentences[] = ['text' => $fee
                    ? 'Katalog mencatat biaya: '.$fee->value.' (status '.$fee->verification.'; diperiksa '.($fee->checked_at?->toDateString() ?? 'tanggal tidak tercatat').').'
                    : 'Biaya publikasi '.$matchedJournal->name.' belum tercatat dalam observasi katalog yang berlaku.',
                    'source_ids' => ['journal:'.$matchedJournal->id]];
            }
            if ($sentences === []) {
                $notice = 'Jurnal ditemukan. Pertanyaan ini belum dapat dijawab langsung dari data terstruktur; periksa sumber terkait.';
            }
        } elseif ($matchedScopus !== null && preg_match('/scopus|indeks|index|discontinued|aktif|active|coverage|cakupan/i', $question)) {
            $sentences[] = [
                'text' => 'Pada daftar sumber Scopus '.$matchedScopus->snapshot_month.', '.$matchedScopus->source_title.' tercatat '.$matchedScopus->status.($matchedScopus->discontinued ? ' dan ditandai discontinued' : '').' dengan cakupan '.($matchedScopus->coverage ?: 'tidak tercatat').'. Periksa status terbaru di sumber resmi sebelum submit.',
                'source_ids' => ['scopus:'.$matchedScopus->id],
            ];
        } elseif (! $ai->ready()) {
            $notice = 'Asisten AI sedang tidak tersedia. Sumber terkait tetap dapat diperiksa di bawah.';
        } else {
            try {
                $sentences = $ai->answerCatalogQuestion($question, $sources);
            } catch (RuntimeException) {
                $notice = 'Jawaban belum dapat diverifikasi. Periksa sumber terkait di bawah.';
            }
        }

        return response()->view('assistant.index', ['question' => $question, 'sources' => $sources,
            'sentences' => $sentences, 'notice' => $notice, 'contextJournal' => $matchedJournal])
            ->header('Cache-Control', 'no-store, private');
    }
}
