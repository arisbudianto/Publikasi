<?php

namespace App\Http\Controllers;

use App\Models\MatchHistory;
use App\Services\DinoikiResearchAi;
use App\Services\ResearchPositioning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ResearchPositioningController extends Controller
{
    public function index(DinoikiResearchAi $ai)
    {
        return $this->page(['ready' => $ai->ready()]);
    }

    public function prepare(Request $request, ResearchPositioning $positioning, DinoikiResearchAi $ai)
    {
        $data = $request->only('title', 'abstract', 'field', 'year_from', 'year_to', 'language', 'consent', 'save_history');
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'min:10', 'max:300'],
            'abstract' => ['required', 'string', 'min:80', 'max:5000'],
            'field' => ['nullable', 'string', 'max:120'],
            'year_from' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'year_to' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'language' => ['required', 'in:id,en'],
            'consent' => ['accepted'],
            'save_history' => ['sometimes', 'accepted'],
        ]);
        $validator->after(function ($validator) use ($data) {
            if (is_numeric($data['year_from'] ?? null) && is_numeric($data['year_to'] ?? null) && (int) $data['year_from'] > (int) $data['year_to']) {
                $validator->errors()->add('year_to', 'Tahun akhir harus sama atau setelah tahun awal.');
            }
        });
        if ($validator->fails()) {
            return $this->page(['inputErrors' => $validator->errors(), 'form' => $data], 422);
        }

        $data = $validator->validated();
        if ($data['language'] === 'en') {
            return $this->analyzeInput($request, $data, $data['title']."\n\n".$data['abstract'], $positioning, $ai);
        }

        try {
            $translated = $positioning->translate($data['title'], $data['abstract']);
        } catch (RuntimeException $exception) {
            return $this->page(['failure' => $exception->getMessage(), 'form' => $data], 503);
        }

        return $this->page(['translation' => $translated, 'form' => $data], 200, 'research-positioning.translation');
    }

    public function analyze(Request $request, ResearchPositioning $positioning, DinoikiResearchAi $ai)
    {
        $data = $request->only('title', 'abstract', 'field', 'year_from', 'year_to', 'translated_query', 'consent', 'save_history');
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'min:10', 'max:300'],
            'abstract' => ['required', 'string', 'min:80', 'max:5000'],
            'field' => ['nullable', 'string', 'max:120'],
            'year_from' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'year_to' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'translated_query' => ['required', 'string', 'min:20', 'max:8000'],
            'consent' => ['accepted'],
            'save_history' => ['sometimes', 'accepted'],
        ]);
        $validator->after(function ($validator) use ($data) {
            if (is_numeric($data['year_from'] ?? null) && is_numeric($data['year_to'] ?? null) && (int) $data['year_from'] > (int) $data['year_to']) {
                $validator->errors()->add('year_to', 'Tahun akhir harus sama atau setelah tahun awal.');
            }
        });
        if ($validator->fails()) {
            return $this->page(['inputErrors' => $validator->errors(), 'form' => $data], 422, 'research-positioning.translation');
        }

        return $this->analyzeInput($request, $validator->validated(), $data['translated_query'], $positioning, $ai);
    }

    private function analyzeInput(Request $request, array $data, string $query, ResearchPositioning $positioning, DinoikiResearchAi $ai)
    {
        if (! $ai->ready()) {
            return $this->page(['failure' => 'Pilot posisi riset belum aktif.'], 503);
        }

        $userKey = 'm15:user:'.$request->user()->getAuthIdentifier().':'.now()->format('Y-m-d');
        $globalKey = 'm15:global:'.now()->format('Y-m-d');
        if (RateLimiter::tooManyAttempts($userKey, (int) config('m15.user_daily_limit', 2))
            || RateLimiter::tooManyAttempts($globalKey, (int) config('m15.global_daily_limit', 4))) {
            return $this->page(['failure' => 'Batas pemeriksaan harian pilot tercapai. Coba lagi besok.'], 429);
        }
        RateLimiter::hit($userKey, now()->endOfDay()->diffInSeconds(now()));
        RateLimiter::hit($globalKey, now()->endOfDay()->diffInSeconds(now()));

        $yearFrom = isset($data['year_from']) && $data['year_from'] !== '' ? (int) $data['year_from'] : null;
        $yearTo = isset($data['year_to']) && $data['year_to'] !== '' ? (int) $data['year_to'] : null;
        $searchQuery = trim($query.' '.($data['field'] ?? ''));
        try {
            $result = $positioning->analyze($data['title'], $data['abstract'], $searchQuery, $yearFrom, $yearTo);
        } catch (RuntimeException $exception) {
            return $this->page(['failure' => $exception->getMessage()], 503);
        }

        $saved = false;
        $saveFailure = false;
        if ($request->boolean('save_history') && $request->user()) {
            try {
                MatchHistory::create([
                    'user_id' => $request->user()->id,
                    'title' => $data['title'],
                    'mode' => 'position',
                    'candidates' => [
                        'type' => 'm15',
                        'mode' => $result['mode'],
                        'searched_at' => $result['searched_at'] ?? now()->utc()->toIso8601String(),
                        'candidate_count' => $result['candidate_count'],
                        'note' => $result['note'],
                        'analysis' => $result['analysis'],
                        'works' => array_map(fn ($work) => [
                            'id' => $work['id'], 'title' => $work['title'], 'doi' => $work['doi'], 'url' => $work['url'],
                            'year' => $work['year'], 'venue' => $work['venue'], 'score' => $work['score'],
                            'abstract_available' => $work['abstract_available'],
                        ], $result['works']),
                    ],
                ]);
                $saved = true;
            } catch (\Throwable) {
                $saveFailure = true;
            }
        }

        return $this->page([
            'form' => ['title' => $data['title'], 'field' => $data['field'] ?? '', 'year_from' => $yearFrom, 'year_to' => $yearTo],
            'inputAbstract' => $data['abstract'],
            'result' => $result,
            'saved' => $saved,
            'saveFailure' => $saveFailure,
        ], 200, 'research-positioning.result');
    }

    private function page(array $data = [], int $status = 200, string $view = 'research-positioning.index')
    {
        return response()->view($view, $data, $status)->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, noarchive');
    }
}
