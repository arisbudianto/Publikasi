<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\SubmissionChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    public const ITEMS = ['scope' => 'Scope dan jenis artikel sesuai panduan jurnal', 'originality' => 'Orisinalitas dan tidak sedang diajukan ke jurnal lain', 'authors' => 'Urutan penulis, afiliasi dan persetujuan semua penulis', 'ethics' => 'Persyaratan etika penelitian diperiksa dan dipenuhi sesuai konteks', 'manuscript' => 'Naskah mengikuti template dan batas kata jurnal', 'references' => 'Sitasi dan daftar pustaka diperiksa', 'figures' => 'Gambar, tabel dan izin penggunaan diperiksa', 'blind' => 'Persyaratan anonimisasi dan identitas penulis diperiksa', 'cover' => 'Cover letter dan deklarasi mengikuti panduan', 'supplements' => 'Berkas tambahan atau data pendukung (bila diminta)'];

    public function show(Request $r, Journal $journal)
    {
        $saved = $r->user() ? SubmissionChecklist::where('user_id', $r->user()->id)->where('journal_id', $journal->id)->first() : null;
        abort_unless($journal->status === 'published' || $saved, 404);

        return response()->view('checklist.show', ['journal' => $journal, 'saved' => $saved, 'items' => self::ITEMS])->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $r, Journal $journal)
    {
        abort_unless($journal->status === 'published', 422, 'Jurnal tidak dipublikasikan; checklist tidak dapat diperbarui.');
        $rules = ['answers' => 'required|array:'.implode(',', array_keys(self::ITEMS)), 'note' => 'nullable|string|max:1000', 'version' => 'required|integer|min:0'];
        foreach (self::ITEMS as $id => $label) {
            $rules['answers.'.$id] = 'required|string|in:pending,done'.($id === 'supplements' ? ',na' : '');
        }$d = $r->validate($rules);
        DB::transaction(function () use ($r, $journal, $d) {
            Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            $saved = SubmissionChecklist::where('user_id', $r->user()->id)->where('journal_id', $journal->id)->first();
            abort_unless((int) ($saved?->version ?? 0) === (int) $d['version'], 409, 'Checklist berubah pada tab lain. Muat ulang sebelum menyimpan.');
            if ($saved) {
                $saved->update(['answers' => $d['answers'], 'note' => $d['note'] ?? null, 'version' => $saved->version + 1]);
            } else {
                SubmissionChecklist::create(['user_id' => $r->user()->id, 'journal_id' => $journal->id, 'answers' => $d['answers'], 'note' => $d['note'] ?? null]);
            }
        });

        return redirect()->route('checklist.show', $journal)->with('success','Checklist pribadi disimpan.');
    }
}
