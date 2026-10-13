<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\M18Guide;
use App\Services\DinoikiResearchAi;
use App\Services\JournalDirectory;
use Illuminate\Http\Request;
use RuntimeException;

class PublicationAssistantController extends Controller
{
    public function index()
    {
        return view('assistant.index');
    }

    public function answer(Request $request, DinoikiResearchAi $ai, JournalDirectory $directory)
    {
        $data = $request->validate(['question' => 'required|string|min:12|max:500']);
        $question = trim($data['question']);
        $words = preg_split('/[^\pL\pN]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_slice(array_values(array_unique(array_filter($words, fn ($word) => mb_strlen($word) >= 4))), 0, 8);
        $sources = [];
        if ($words !== []) {
            $journals = Journal::query()->where('status', 'published')->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $escaped = addcslashes($word, '%_\\');
                    $query->orWhere('name', 'like', '%'.$escaped.'%')
                        ->orWhere('subject', 'like', '%'.$escaped.'%')
                        ->orWhere('scope_summary', 'like', '%'.$escaped.'%');
                }
            })->limit(6)->get();
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
            $guides = M18Guide::query()->whereNotNull('published_at')->where('published_at', '<=', now())
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
        } elseif (! $ai->ready()) {
            $notice = 'Asisten AI sedang tidak tersedia. Sumber terkait tetap dapat diperiksa di bawah.';
        } else {
            try {
                $sentences = $ai->answerCatalogQuestion($question, $sources);
            } catch (RuntimeException) {
                $notice = 'Jawaban belum dapat diverifikasi. Periksa sumber terkait di bawah.';
            }
        }

        return response()->view('assistant.index', compact('question', 'sources', 'sentences', 'notice'))
            ->header('Cache-Control', 'no-store, private');
    }
}
