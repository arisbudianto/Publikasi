<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    private function owned(Request $r, int $submission): Submission
    {
        return Submission::where('user_id', $r->user()->id)->findOrFail($submission);
    }

    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $search = (string) $r->query('q', '');

        return response()->view('submissions.index', [
            'submissions' => Submission::with('journal')->where('user_id', $r->user()->id)->latest()->paginate(12),
            'journals' => Journal::where('status', 'published')->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('name')->limit(100)->get(['id', 'name']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $r)
    {
        $d = $r->validate(['title' => 'required|string|max:255', 'journal_id' => ['required', 'integer', Rule::exists('journals', 'id')->where('status', 'published')], 'reference' => 'nullable|string|max:150', 'note' => 'nullable|string|max:2000', 'due_at' => 'nullable|date_format:Y-m-d']);
        $s = DB::transaction(function () use ($r, $d) {
            Journal::where('status', 'published')->lockForUpdate()->findOrFail($d['journal_id']);

            return Submission::create($d + ['user_id' => $r->user()->id, 'status' => 'draft']);
        });

        return redirect()->route('submissions.show', $s)->with('success', 'Pelacak pribadi dibuat. Artikel belum dikirim oleh aplikasi.');
    }

    public function show(Request $r, int $submission)
    {
        $s = $this->owned($r, $submission)->load('journal');

        return response()->view('submissions.show', ['submission' => $s, 'events' => $s->events()->orderByDesc('id')->paginate(25, ['*'], 'event_page'), 'responses' => $s->responses()->orderBy('round')->orderBy('id')->paginate(20, ['*'], 'response_page')])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $r, int $submission)
    {
        $this->owned($r, $submission);
        $d = $r->validate(['title' => 'required|string|max:255', 'reference' => 'nullable|string|max:150', 'note' => 'nullable|string|max:2000', 'due_at' => 'nullable|date_format:Y-m-d', 'status' => ['required', Rule::in(array_keys(Submission::LABELS))], 'occurred_at' => 'nullable|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->format('Y-m-d'), 'event_note' => 'nullable|string|max:2000', 'version' => 'required|integer|min:1']);
        DB::transaction(function () use ($r, $submission, $d) {
            $s = Submission::where('user_id', $r->user()->id)->lockForUpdate()->findOrFail($submission);
            abort_unless($s->version === (int) $d['version'], 409, 'Pelacak berubah pada tab lain. Muat ulang sebelum menyimpan.');
            if ($d['status'] !== $s->status) {
                if (! in_array($d['status'], Submission::TRANSITIONS[$s->status], true)) {
                    throw ValidationException::withMessages(['status' => 'Perubahan status tidak diizinkan. Buat pelacak baru untuk pengajuan lain.']);
                }
                $last = $s->events()->max('occurred_at');
                if (empty($d['occurred_at']) || ($last && $d['occurred_at'] < substr($last, 0, 10))) {
                    throw ValidationException::withMessages(['occurred_at' => 'Isi tanggal kejadian, tidak lebih awal dari timeline terakhir.']);
                }
                $s->events()->create(['from_status' => $s->status, 'to_status' => $d['status'], 'occurred_at' => $d['occurred_at'], 'note' => $d['event_note'] ?? null]);
            }
            $s->update(collect($d)->only(['title', 'reference', 'note', 'due_at', 'status'])->all() + ['version' => $s->version + 1]);
        });

        return redirect()->route('submissions.show', $submission)->with('success', 'Pelacak diperbarui.');
    }

    public function responseStore(Request $r, int $submission)
    {
        $this->owned($r, $submission);
        $d = $this->responseData($r);
        DB::transaction(function () use ($r, $submission, $d) {
            $s = Submission::where('user_id', $r->user()->id)->lockForUpdate()->findOrFail($submission);
            abort_if($s->responses()->count() >= 500, 422, 'Batas 500 baris per pelacak.');
            $s->responses()->create($d);
        });

        return redirect()->route('submissions.show', $submission)->with('success', 'Komentar reviewer ditambahkan.');
    }

    public function responseUpdate(Request $r, int $submission, int $response)
    {
        $s = $this->owned($r, $submission);
        $s->responses()->findOrFail($response);
        $d = $this->responseData($r);
        $r->validate(['version' => 'required|integer|min:1']);
        DB::transaction(function () use ($s, $response, $r, $d) {
            $item = $s->responses()->lockForUpdate()->findOrFail($response);
            abort_unless($item->version === (int) $r->input('version'), 409, 'Respons berubah pada tab lain. Muat ulang.');
            $item->update($d + ['version' => $item->version + 1]);
        });

        return redirect()->route('submissions.show', $submission)->with('success', 'Respons reviewer diperbarui.');
    }

    private function responseData(Request $r): array
    {
        return $r->validate(['round' => 'required|integer|min:1|max:99', 'reviewer' => 'required|string|max:100', 'comment' => 'required|string|max:5000', 'status' => ['required', Rule::in(['pending', 'addressed'])], 'response' => 'nullable|string|max:5000|required_if:status,addressed', 'location' => 'nullable|string|max:500|required_if:status,addressed']);
    }

    public function export(Request $r, int $submission)
    {
        $s = $this->owned($r, $submission);

        return response()->streamDownload(function () use ($s) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Putaran', 'Reviewer', 'Komentar', 'Respons', 'Lokasi revisi', 'Status'], ',', '"', '');
            foreach ($s->responses()->orderBy('round')->orderBy('id')->cursor() as $row) {
                $cells = [$row->round, $row->reviewer, $row->comment, $row->response, $row->location, $row->status];
                $cells = array_map(fn ($v) => preg_match('/^[\s\x00-\x1f]*[=+@-]/u', (string) $v) ? "'".$v : (string) $v, $cells);
                fputcsv($out, $cells, ',', '"', '');
            }
            fclose($out);
        }, 'respons-reviewer-'.$s->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
