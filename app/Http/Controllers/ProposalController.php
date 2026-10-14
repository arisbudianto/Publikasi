<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\M18Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    public const OBSERVATION_FIELDS = ['index', 'fee', 'time', 'language', 'frequency'];

    public function create(Request $request)
    {
        $journal = $request->integer('journal') ? Journal::where('status', 'published')->findOrFail($request->integer('journal')) : null;

        return response()->view('proposals.create', compact('journal'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['new_journal', 'index', 'fee', 'time', 'language', 'frequency', 'scope', 'identifier'])],
            'journal_id' => ['nullable', 'required_unless:field,new_journal', Rule::exists('journals', 'id')->where('status', 'published')],
            'journal_name' => 'required_if:field,new_journal|nullable|string|min:5|max:255',
            'proposed_value' => 'required|string|min:3|max:1000',
            'source_url' => 'required|url:https|max:1000',
            'note' => 'nullable|string|max:2000',
        ]);
        if ($data['field'] === 'new_journal' && filled($data['journal_id'] ?? null)) {
            return back()->withErrors(['journal_id' => 'Jurnal baru tidak boleh memilih jurnal yang sudah ada.'])->withInput();
        }
        $journal = isset($data['journal_id']) ? Journal::findOrFail($data['journal_id']) : null;

        DB::transaction(function () use ($data, $journal, $request) {
            $proposal = M18Proposal::create([
                'user_id' => $request->user()->id, 'journal_id' => $journal?->id,
                'journal_name' => $journal?->name ?? $data['journal_name'],
                'field' => $data['field'], 'proposed_value' => $data['proposed_value'],
                'source_url' => $data['source_url'], 'note' => $data['note'] ?? null,
            ]);
            $proposal->events()->create(['actor_id' => $request->user()->id,
                'to_status' => 'new', 'created_at' => now()]);
        });

        return redirect()->route('proposals.mine')->with('success', 'Usulan masuk antrean kurasi. Data katalog belum berubah.');
    }

    public function assistantFeedback(Request $request)
    {
        $data = $request->validate([
            'feedback' => 'required|string|min:10|max:1000',
            'journal_id' => ['nullable', Rule::exists('journals', 'id')->where('status', 'published')],
        ]);
        $journal = isset($data['journal_id']) ? Journal::findOrFail($data['journal_id']) : null;

        DB::transaction(function () use ($request, $data, $journal) {
            $proposal = M18Proposal::create([
                'user_id' => $request->user()->id, 'journal_id' => $journal?->id,
                'journal_name' => $journal?->name ?? 'Jawaban asisten publikasi',
                'field' => 'assistant_feedback', 'proposed_value' => $data['feedback'],
                'source_url' => route('assistant.index'),
                'note' => 'Laporan jawaban asisten; teks pertanyaan dan jawaban tidak disimpan.',
            ]);
            $proposal->events()->create(['actor_id' => $request->user()->id,
                'to_status' => 'new', 'created_at' => now()]);
        });

        return redirect()->route('proposals.mine')->with('success', 'Laporan masuk antrean tinjau.');
    }

    public function mine(Request $request)
    {
        return response()->view('proposals.mine', ['proposals' => M18Proposal::where('user_id', $request->user()->id)
            ->latest('id')->paginate(20)])->header('Cache-Control', 'no-store, private');
    }

    public function admin(Request $request)
    {
        $status = $request->validate(['status' => 'nullable|in:new,reviewed,applied,rejected'])['status'] ?? 'new';

        return response()->view('proposals.admin', ['status' => $status,
            'proposals' => M18Proposal::with('journal')->where('status', $status)->latest('id')->paginate(20)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(M18Proposal $proposal)
    {
        return response()->view('proposals.show', ['proposal' => $proposal->load(['journal', 'events'])])
            ->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, M18Proposal $proposal)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['reviewed', 'rejected'])],
            'decision_note' => 'required|string|min:20|max:2000',
        ]);
        DB::transaction(function () use ($proposal, $data, $request) {
            $locked = M18Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'new', 409);
            $locked->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id,
                'decision_note' => $data['decision_note']]);
            $locked->events()->create(['actor_id' => $request->user()->id, 'from_status' => 'new',
                'to_status' => $data['decision'], 'note' => $data['decision_note'], 'created_at' => now()]);
        });

        return redirect()->route('admin.proposals.show', $proposal)->with('success', 'Keputusan dicatat. Katalog belum berubah.');
    }

    public function markApplied(Request $request, M18Proposal $proposal)
    {
        $data = $request->validate([
            'journal_id' => ['required', Rule::exists('journals', 'id')],
            'decision_note' => 'required|string|min:20|max:2000',
        ]);
        DB::transaction(function () use ($proposal, $data, $request) {
            $locked = M18Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'reviewed' && $locked->field !== 'assistant_feedback'
                && ! in_array($locked->field, self::OBSERVATION_FIELDS, true), 409);
            if ($locked->journal_id) {
                abort_unless($locked->journal_id === (int) $data['journal_id'], 422);
            }
            $locked->update(['status' => 'applied', 'journal_id' => $data['journal_id'],
                'reviewed_by' => $request->user()->id, 'decision_note' => $data['decision_note']]);
            $locked->events()->create(['actor_id' => $request->user()->id, 'from_status' => 'reviewed',
                'to_status' => 'applied', 'note' => $data['decision_note'], 'created_at' => now()]);
        });

        return redirect()->route('admin.proposals.show', $proposal)->with('success', 'Penerapan manual dicatat.');
    }
}
