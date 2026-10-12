<?php

namespace App\Http\Controllers;

use App\Services\BasicMatcher;
use App\Services\DiscontinuedSourceLookup;
use App\Services\PrivateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatchController extends Controller
{
    public function form()
    {
        return response()->view('match.index')->header('Cache-Control', 'no-store, private');
    }

    public function match(Request $request, BasicMatcher $matcher, DiscontinuedSourceLookup $discontinuedLookup)
    {
        $validator = Validator::make($request->only('title', 'abstract', 'context'), [
            'title' => 'required|string|min:10|max:300',
            'abstract' => 'required|string|min:80|max:10000',
            'context' => 'nullable|string|max:1500',
        ]);
        // No withInput: manuscript content must not be flashed into the session.
        if ($validator->fails()) {
            return response()->view('match.index', ['inputErrors' => $validator->errors()], 422)->header('Cache-Control', 'no-store, private');
        }
        $data = $validator->validated();
        $results = $matcher->match($data['title'], $data['abstract'], $data['context'] ?? '');

        if ($request->user() && $request->boolean('save_history')) {
            app(PrivateCatalog::class)->record($request->user(), $data['title'], 'keyword', $results);
        }

        $discontinuedByJournal = $discontinuedLookup->forJournals(array_column($results, 'journal'));

        return response()->view('match.index', compact('results', 'discontinuedByJournal'))->header('Cache-Control', 'no-store, private');
    }
}
