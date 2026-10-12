<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalDiscontinuedSource;
use App\Rules\ValidIssn;
use App\Services\JournalIntegrityEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class JournalIntegrityController extends Controller
{
    public function index()
    {
        return response()->view('integrity.form', ['criteria' => app(JournalIntegrityEvaluator::class)->criteria()])->header('Cache-Control', 'no-store, private');
    }

    public function discontinued(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:180']);
        $term = trim($data['q'] ?? '');
        $query = JournalDiscontinuedSource::where('is_current', true)->orderBy('source_title');
        if ($term !== '') {
            $normalized = strtoupper($term);
            $issn = preg_match('/^\d{4}-?\d{3}[\dX]$/', $normalized) ? substr(str_replace('-', '', $normalized), 0, 4).'-'.substr(str_replace('-', '', $normalized), 4) : null;
            $query->where(fn ($match) => $issn
                ? $match->where('issn', $issn)
                : $match->where('source_title', 'like', '%'.addcslashes($term, '%_\\').'%'));
        }
        $sources = $query->paginate(20)->withQueryString();
        $latestSnapshot = JournalDiscontinuedSource::max('source_as_of');

        return response()->view('integrity.discontinued', compact('sources', 'term', 'latestSnapshot'))->header('Cache-Control', 'no-store, private');
    }

    public function check(Request $request, JournalIntegrityEvaluator $evaluator)
    {
        $criteria = $evaluator->criteria();
        $rules = ['lookup' => 'required|string|min:2|max:180', 'save_assessment' => 'sometimes|accepted'];
        foreach (array_keys($criteria) as $key) {
            $rules['answers.'.$key] = ['required', Rule::in(['yes', 'no', 'unknown'])];
        }
        $validator = Validator::make($request->only(['lookup', 'answers', 'save_assessment']), $rules);
        if ($validator->fails()) {
            return response()->view('integrity.form', ['criteria' => $criteria, 'inputErrors' => $validator->errors()], 422)->header('Cache-Control', 'no-store, private');
        }
        $data = $validator->validated();
        $answers = $data['answers'];
        $result = $evaluator->evaluate($answers);
        $lookup = trim($data['lookup']);
        $normalized = strtoupper(preg_replace('/\s+/', '', $lookup));
        $issnLike = preg_match('/^\d{4}-?\d{3}[\dX]$/', $normalized) === 1;
        $issn = $issnLike ? (str_replace('-', '', $normalized)) : null;
        if ($issn) {
            $issn = substr($issn, 0, 4).'-'.substr($issn, 4);
            $issnValidator = Validator::make(['issn' => $issn], ['issn' => ['required', new ValidIssn]]);
            if ($issnValidator->fails()) {
                return response()->view('integrity.form', ['criteria' => $criteria, 'inputErrors' => $issnValidator->errors()], 422)->header('Cache-Control', 'no-store, private');
            }
        }

        $matchesQuery = Journal::where('status', 'published')->where(function ($query) use ($lookup, $issn) {
            if ($issn) {
                $query->where('issn', $issn)->orWhereHas('identifiers', fn ($identifier) => $identifier->where('value', $issn));
            } else {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($lookup)]);
            }
        });
        $journals = $matchesQuery->with('identifiers')->limit(5)->get();
        $journal = $journals->count() === 1 ? $journals->first() : null;

        $sourceQuery = JournalDiscontinuedSource::where('is_current', true)->orderBy('coverage_end_year');
        if ($issn) {
            $sources = $sourceQuery->where('issn', $issn)->get();
        } else {
            $sources = $sourceQuery->where('source_title', 'like', '%'.addcslashes($lookup, '%_\\').'%')->limit(10)->get();
        }

        if ($request->user() && $request->boolean('save_assessment')) {
            $request->user()->integrityAssessments()->create([
                'journal_id' => $journal?->id,
                'criteria_version' => $result['version'],
                'answers' => $answers,
                'risk_score' => $result['score'],
                'unknown_count' => $result['unknown_count'],
                'outcome' => $result['outcome'],
            ]);
            $result['saved'] = true;
        }

        return response()->view('integrity.result', compact('criteria', 'answers', 'result', 'journals', 'journal', 'sources'))->header('Cache-Control', 'no-store, private');
    }

    public function removeAssessment(Request $request, int $assessment)
    {
        $request->user()->integrityAssessments()->findOrFail($assessment)->delete();

        return back()->with('success', 'Hasil pemeriksaan dihapus dari akun Anda.');
    }
}
