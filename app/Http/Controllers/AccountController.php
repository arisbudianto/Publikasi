<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalIntegrityAssessment;
use App\Models\MatchHistory;
use App\Models\PersonalShortlist;
use App\Models\SubmissionChecklist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    private function page(string $view, array $data = [], int $status = 200)
    {
        if ($view === 'account.index') {
            $data['checklists'] = SubmissionChecklist::with('journal')->where('user_id', auth()->id())->latest()->paginate(10, ['*'], 'checklist_page');
            $data['integrityAssessments'] ??= JournalIntegrityAssessment::with('journal')->where('user_id', auth()->id())->latest()->paginate(10, ['*'], 'integrity_page');
        }

        return response()->view($view, $data, $status)->header('Cache-Control', 'no-store, private');
    }

    public function loginForm()
    {
        return $this->page('account.auth', ['register' => false]);
    }

    public function registerForm()
    {
        return $this->page('account.auth', ['register' => true]);
    }

    public function register(Request $r)
    {
        $data = $r->only('name', 'email', 'password', 'password_confirmation');
        if (is_string($data['email'] ?? null)) {
            $data['email'] = Str::lower(trim($data['email']));
        }
        $v = Validator::make($data, ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12|max:128|confirmed']);
        if ($v->fails()) {
            return $this->page('account.auth', ['register' => true, 'inputErrors' => $v->errors()], 422);
        }
        $d = $v->validated();
        $user = new User;
        $user->name = $d['name'];
        $user->email = $d['email'];
        $user->password = $d['password'];
        $user->is_admin = false;
        $user->save();
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('account.index');
    }

    public function login(Request $r)
    {
        $v = Validator::make($r->only('email', 'password'), ['email' => 'required|email|max:255', 'password' => 'required|string|max:128']);
        if ($v->fails()) {
            return $this->page('account.auth', ['register' => false, 'inputErrors' => $v->errors()], 422);
        }
        $d = $v->validated();
        $d['email'] = Str::lower(trim($d['email']));
        $key = 'account-login|'.$d['email'].'|'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->page('account.auth', ['register' => false, 'failure' => 'Terlalu banyak percobaan. Coba satu menit kemudian.'], 429);
        }
        if (! Auth::attempt($d)) {
            RateLimiter::hit($key, 60);

            return $this->page('account.auth', ['register' => false, 'failure' => 'Email atau password tidak sesuai.'], 422);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect()->route('account.index');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function index(Request $r)
    {
        return $this->page('account.index', ['shortlists' => PersonalShortlist::with('journal')->where('user_id', $r->user()->id)->latest()->paginate(12, ['*'], 'shortlist_page'), 'histories' => MatchHistory::where('user_id', $r->user()->id)->latest()->paginate(10, ['*'], 'history_page'), 'integrityAssessments' => JournalIntegrityAssessment::with('journal')->where('user_id', $r->user()->id)->latest()->paginate(10, ['*'], 'integrity_page')]);
    }

    public function saveJournal(Request $r, Journal $journal)
    {
        abort_unless($journal->status === 'published', 404);
        PersonalShortlist::firstOrCreate(['user_id' => $r->user()->id, 'journal_id' => $journal->id]);

        return back()->with('success', 'Jurnal disimpan ke shortlist pribadi.');
    }

    public function removeJournal(Request $r, int $shortlist)
    {
        $item = PersonalShortlist::where('user_id', $r->user()->id)->findOrFail($shortlist);
        $item->delete();

        return back()->with('success', 'Jurnal dihapus dari shortlist.');
    }

    public function removeHistory(Request $r, int $history)
    {
        $item = MatchHistory::where('user_id', $r->user()->id)->findOrFail($history);
        $item->delete();

        return back()->with('success', 'Riwayat dihapus.');
    }

    public function profile(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100']);
        $r->user()->update($d);

        return back()->with('success', 'Nama diperbarui.');
    }

    public function password(Request $r)
    {
        $v = Validator::make($r->only('current_password', 'password', 'password_confirmation'), ['current_password' => 'required|string|current_password', 'password' => 'required|string|min:12|max:128|confirmed']);
        if ($v->fails()) {
            return $this->page('account.index', ['shortlists' => PersonalShortlist::with('journal')->where('user_id', $r->user()->id)->latest()->paginate(12, ['*'], 'shortlist_page'), 'histories' => MatchHistory::where('user_id', $r->user()->id)->latest()->paginate(10, ['*'], 'history_page'), 'inputErrors' => $v->errors()], 422);
        }
        Auth::logoutOtherDevices($v->validated()['current_password']);
        $r->user()->update(['password' => $v->validated()['password']]);
        $r->session()->regenerate();

        return back()->with('success', 'Password diperbarui.');
    }
}
