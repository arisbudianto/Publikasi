<?php

namespace App\Http\Controllers;

use App\Services\SemanticMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SemanticController extends Controller
{
    public function form()
    {
        return $this->page();
    }

    private function page(array $data = [], int $status = 200)
    {
        return response()->view('match.semantic', $data + ['ready' => (bool) (config('semantic.enabled') && config('semantic.key'))], $status)->header('Cache-Control', 'no-store, private');
    }

    public function match(Request $r, SemanticMatcher $matcher)
    {
        $v = Validator::make($r->only('title', 'abstract', 'context', 'consent'), ['title' => 'required|string|min:10|max:300', 'abstract' => 'required|string|min:80|max:5000', 'context' => 'nullable|string|max:500', 'consent' => 'accepted']);
        if ($v->fails()) {
            return $this->page(['inputErrors' => $v->errors()], 422);
        }
        if (! config('semantic.enabled') || ! config('semantic.key')) {
            return $this->page(['failure' => 'Mode AI belum diaktifkan. Pencocokan dasar tetap tersedia.'], 503);
        }
        $limit = 'semantic-global-'.now()->format('Y-m-d');
        if (RateLimiter::tooManyAttempts($limit, config('semantic.daily_limit'))) {
            return $this->page(['failure' => 'Batas permintaan AI harian tercapai. Gunakan pencocokan dasar.'], 429);
        }
        RateLimiter::hit($limit, 86400);
        $data = $v->validated();
        try {
            return $this->page(['results' => $matcher->match($data['title'], $data['abstract'], $data['context'] ?? '')]);
        } catch (RuntimeException $e) {
            return $this->page(['failure' => $e->getMessage()], 503);
        }
    }
}
