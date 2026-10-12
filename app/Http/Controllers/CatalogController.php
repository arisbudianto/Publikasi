<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalObservation;
use App\Models\JournalSource;
use App\Services\Catalog;
use App\Services\JournalDirectory;
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

    private function directoryRules(): array
    {
        return array_merge($this->catalogRules(), [
            'rank_scheme' => 'nullable|string|max:80', 'rank_value' => 'nullable|integer|min:1|max:100',
            'indexation' => 'nullable|string|max:120', 'fee_route' => 'nullable|in:apc_oa,subscription,submission_fee,other',
            'apc_max_minor' => 'nullable|integer|min:0|max:999999999999999',
            'apc_currency' => ['nullable', 'required_with:apc_max_minor', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'language' => 'nullable|string|max:100', 'frequency_min' => 'nullable|integer|min:1|max:365',
            'frequency_max' => 'nullable|integer|min:1|max:365', 'verification' => 'nullable|in:verified,publisher_claim',
            'sort' => 'nullable|in:name,rank,cost,checked',
        ]);
    }

    public function index(Request $r, JournalDirectory $directory)
    {
        $filters = $r->validate($this->directoryRules());
        if (($filters['sort'] ?? '') === 'rank' && empty($filters['rank_scheme'])) {
            throw ValidationException::withMessages(['rank_scheme' => 'Pilih skema peringkat sebelum mengurutkan berdasarkan peringkat.']);
        }
        if (($filters['sort'] ?? '') === 'cost' && empty($filters['apc_currency'])) {
            throw ValidationException::withMessages(['apc_currency' => 'Pilih mata uang untuk mengurutkan biaya.']);
        }
        if (isset($filters['frequency_min'], $filters['frequency_max']) && $filters['frequency_min'] > $filters['frequency_max']) {
            throw ValidationException::withMessages(['frequency_max' => 'Frekuensi maksimum harus sama atau lebih besar dari minimum.']);
        }
        $q = trim($filters['q'] ?? '');
        $selectedIds = array_map('intval', $filters['ids'] ?? []);
        $selected = Journal::where('status', 'published')->whereIn('id', $selectedIds)->get()->sortBy(fn ($j) => array_search($j->id, $selectedIds))->values();
        abort_unless($selected->count() === count($selectedIds), 404);
        $subjects = Journal::where('status', 'published')->distinct()->orderBy('subject')->pluck('subject');
        $publishers = Journal::where('status', 'published')->distinct()->orderBy('publisher')->pluck('publisher');
        $query = $directory->search(Journal::where('status', 'published'), $filters);
        if (! empty($filters['information']) && ! empty($filters['availability'])) {
            $method = $filters['availability'] === 'known' ? 'whereHas' : 'whereDoesntHave';
            $query->$method('observations', fn ($b) => $b->where('kind', $filters['information'])->whereNotNull('value')->where('value', '!=', ''));
        }
        $directory->filter($query, $filters);
        $journals = $directory->sort($query, $filters)->with([
            'observations' => fn ($observations) => $directory->currentObservations($observations),
            'identifiers',
        ])->paginate(12)->withQueryString();

        $sourceCount = JournalSource::count();

        $options = $directory->options();
        $activeFilterCount = count(array_filter($filters, fn ($value, $key) => $key !== 'ids' && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH));

        return view('catalog.index', compact('journals', 'q', 'filters', 'subjects', 'publishers', 'selected', 'selectedIds', 'sourceCount', 'options', 'activeFilterCount'));
    }

    public function noApc(Request $r, JournalDirectory $directory)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:120', 'subject' => 'nullable|string|max:255', 'language' => 'nullable|string|max:100', 'ids' => 'sometimes|array|max:3', 'ids.*' => 'required|integer|min:1|distinct']);
        $ids = array_map('intval', $filters['ids'] ?? []);
        $selected = Journal::where('status', 'published')->whereIn('id', $ids)->get()->sortBy(fn ($journal) => array_search($journal->id, $ids))->values();
        abort_unless($selected->count() === count($ids), 404);

        $free = Journal::where('status', 'published');
        $directory->search($free, $filters);
        $free->whereHas('observations', fn ($observation) => $directory->currentObservations($observation)->where('kind', 'fee')->where('fee_route', 'apc_oa')->where('amount_minor', 0)->where('verification', 'verified'));

        $unknown = Journal::where('status', 'published');
        $directory->search($unknown, $filters);
        $unknown->whereDoesntHave('observations', fn ($observation) => $directory->currentObservations($observation)->where('kind', 'fee')->where('fee_route', 'apc_oa')->whereNotNull('amount_minor')->where('verification', 'verified'));

        $free = $free->with(['observations' => fn ($observations) => $directory->currentObservations($observations), 'identifiers'])->orderBy('name')->paginate(12, ['*'], 'free_page')->withQueryString();
        $unknown = $unknown->with(['observations' => fn ($observations) => $directory->currentObservations($observations), 'identifiers'])->orderBy('name')->paginate(12, ['*'], 'unknown_page')->withQueryString();
        $subjects = Journal::where('status', 'published')->distinct()->orderBy('subject')->pluck('subject');
        $options = $directory->options();
        $activeFilterCount = count(array_filter($filters, fn ($value) => $value !== null && $value !== ''));
        $dataCheckedAt = JournalObservation::query()->where('kind', 'fee')->where('verification', 'verified')->whereNotNull('checked_at')->max('checked_at');

        return view('catalog.no-apc', compact('filters', 'free', 'unknown', 'subjects', 'options', 'activeFilterCount', 'selected', 'ids', 'dataCheckedAt'));
    }

    public function compare(Request $r)
    {
        $data = $r->validate(array_merge($this->directoryRules(), ['ids' => 'required|array|min:1|max:3', 'ids.*' => 'required|integer|min:1|distinct', 'origin' => 'nullable|in:katalog,tanpa-apc']));
        $ids = array_map('intval', $data['ids']);
        $journals = Journal::with(['observations', 'identifiers'])->where('status', 'published')->whereIn('id', $ids)->get()->sortBy(fn ($j) => array_search($j->id, $ids))->values();
        abort_unless($journals->count() === count($ids), 404);

        $returnFilters = $r->only(['q', 'subject', 'publisher', 'information', 'availability', 'rank_scheme', 'rank_value', 'indexation', 'fee_route', 'apc_max_minor', 'apc_currency', 'language', 'frequency_min', 'frequency_max', 'verification', 'sort']);
        $origin = $r->input('origin') === 'tanpa-apc' ? 'catalog.no-apc' : 'catalog.index';

        return view('catalog.compare', compact('journals', 'ids', 'returnFilters', 'origin'));
    }

    public function show(Journal $journal)
    {
        abort_unless($journal->status === 'published' || auth()->user()?->is_admin, 404);
        $journal->load(['observations', 'accreditations', 'identifiers']);

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
