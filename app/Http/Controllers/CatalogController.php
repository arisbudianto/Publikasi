<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalSource;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    private function catalogRules(): array
    {
        return ['q' => 'nullable|string|max:120', 'subject' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255', 'information' => 'nullable|required_with:availability|in:index,fee,time',
            'availability' => 'nullable|required_with:information|in:known,unknown', 'ids' => 'sometimes|array|max:3',
            'ids.*' => 'required|integer|min:1|distinct'];
    }

    public function index(Request $r)
    {
        $filters = $r->validate($this->catalogRules());
        $q = trim($filters['q'] ?? '');
        $selectedIds = array_map('intval', $filters['ids'] ?? []);
        $selected = Journal::where('status', 'published')->whereIn('id', $selectedIds)->get()->sortBy(fn ($j) => array_search($j->id, $selectedIds))->values();
        abort_unless($selected->count() === count($selectedIds), 404);
        $subjects = Journal::where('status', 'published')->distinct()->orderBy('subject')->pluck('subject');
        $publishers = Journal::where('status', 'published')->distinct()->orderBy('publisher')->pluck('publisher');
        $query = Journal::where('status', 'published')
            ->when($q !== '', fn ($b) => $b->where(fn ($x) => $x->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('issn', $q)))
            ->when($filters['subject'] ?? '', fn ($b, $v) => $b->where('subject', $v))
            ->when($filters['publisher'] ?? '', fn ($b, $v) => $b->where('publisher', $v));
        if (! empty($filters['information']) && ! empty($filters['availability'])) {
            $method = $filters['availability'] === 'known' ? 'whereHas' : 'whereDoesntHave';
            $query->$method('observations', fn ($b) => $b->where('kind', $filters['information'])->whereNotNull('value')->where('value', '!=', ''));
        }
        $journals = $query->orderBy('name')->paginate(12)->withQueryString();

        $sourceCount = JournalSource::count();

        return view('catalog.index', compact('journals', 'q', 'filters', 'subjects', 'publishers', 'selected', 'selectedIds', 'sourceCount'));
    }

    public function compare(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1|max:3', 'ids.*' => 'required|integer|min:1|distinct']);
        $ids = array_map('intval', $data['ids']);
        $journals = Journal::with('observations')->where('status', 'published')->whereIn('id', $ids)->get()->sortBy(fn ($j) => array_search($j->id, $ids))->values();
        abort_unless($journals->count() === count($ids), 404);

        return view('catalog.compare', compact('journals', 'ids'));
    }

    public function show(Journal $journal)
    {
        abort_unless($journal->status === 'published' || auth()->user()?->is_admin, 404);
        $journal->load(['observations', 'accreditations']);

        return view('catalog.show', compact('journal'));
    }

    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = Str::lower($data['email']).'|'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi satu menit kemudian.']);
        }
        if (! Auth::attempt($data)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau password tidak sesuai.']);
        }
        if (! $r->user()->is_admin) {
            Auth::logout();
            $r->session()->invalidate();
            throw ValidationException::withMessages(['email' => 'Akun tidak memiliki akses admin.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect()->route('admin.index');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function admin()
    {
        return view('admin.index', ['journals' => Journal::orderByDesc('updated_at')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.form', ['journal' => new Journal(['status' => 'draft', 'checked_at' => today()])]);
    }

    public function edit(Journal $journal)
    {
        return view('admin.form', compact('journal'));
    }

    public function store(Request $r, Catalog $catalog)
    {
        $data = validator($catalog->normalize($r->only(Catalog::FIELDS)), $catalog->rules())->validate();
        $catalog->save($data, null, $r->user()->id);

        return redirect()->route('admin.index')->with('success', 'Jurnal ditambahkan.');
    }

    public function update(Request $r, Journal $journal, Catalog $catalog)
    {
        $data = validator($catalog->normalize($r->only(Catalog::FIELDS)), $catalog->rules($journal))->validate();
        $catalog->save($data, $journal, $r->user()->id);

        return redirect()->route('admin.index')->with('success', 'Jurnal diperbarui.');
    }

    public function archive(Request $r, Journal $journal, Catalog $catalog)
    {
        $catalog->save(['status' => 'archived'], $journal, $r->user()->id);

        return back()->with('success', 'Jurnal diarsipkan; data tetap tersimpan.');
    }

    public function importForm()
    {
        return view('admin.import');
    }

    public function preview(Request $r, Catalog $catalog)
    {
        $r->session()->forget('catalog_import');
        $r->validate(['csv' => 'required|file|mimes:csv,txt|max:1024']);
        $preview = $catalog->preview($r->file('csv')->getRealPath());
        if (! $preview['errors']) {
            $r->session()->put('catalog_import', ['rows' => $preview['rows'], 'expires' => now()->addMinutes(15)->timestamp]);
        }

        return view('admin.import', compact('preview'));
    }

    public function commitImport(Request $r, Catalog $catalog)
    {
        $p = $r->session()->get('catalog_import');
        abort_unless($p && $p['expires'] >= time(), 422, 'Preview kedaluwarsa. Unggah ulang CSV.');
        $catalog->import($p['rows'], $r->user()->id);
        $r->session()->forget('catalog_import');

        return redirect()->route('admin.index')->with('success', 'Seluruh baris berhasil diimpor.');
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $h = fopen('php://output', 'w');
            fputcsv($h, Catalog::FIELDS, ',', '"', '');
            fclose($h);
        }, 'template-jurnal.csv', ['Content-Type' => 'text/csv']);
    }
}
