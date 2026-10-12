<?php

namespace App\Http\Controllers;

use App\Models\SupportPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('payments.enabled') && is_string(config('payments.instructions')) && trim(config('payments.instructions')) !== '';
    }

    public function index(Request $r)
    {
        return response()->view('payments.index', ['payments' => SupportPayment::with('events')->where('user_id', $r->user()->id)->latest()->paginate(10), 'enabled' => self::enabled()])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $r)
    {
        abort_unless(self::enabled(), 503, 'Pembayaran opsional belum diaktifkan.');
        $d = $r->validate(['amount_idr' => 'required|integer|min:10000|max:10000000', 'acknowledge' => 'accepted']);
        DB::transaction(function () use ($r, $d) {
            // Serialize creation for the same account; duplicate retry cannot create two pending intents.
            $r->user()->newQuery()->whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            abort_if(SupportPayment::where('user_id', $r->user()->id)->where('status', 'pending')->exists(), 409, 'Masih ada permintaan menunggu verifikasi.');
            $p = SupportPayment::create(['user_id' => $r->user()->id, 'amount_idr' => $d['amount_idr'], 'status' => 'pending']);
            $p->events()->create(['actor_id' => $r->user()->id, 'status' => 'pending']);
        });

        return redirect()->route('payments.index')->with('success', 'Permintaan dicatat. Belum ada pembayaran terverifikasi.');
    }

    public function cancel(Request $r, int $payment)
    {
        $r->validate(['version' => 'required|integer|min:1']);
        DB::transaction(function () use ($r, $payment) {
            $p = SupportPayment::where('user_id', $r->user()->id)->lockForUpdate()->findOrFail($payment);
            abort_unless($p->version === (int) $r->input('version'), 409, 'Status berubah. Muat ulang.');
            abort_unless($p->status === 'pending', 409, 'Permintaan ini sudah diproses.');
            $p->update(['status' => 'cancelled', 'version' => $p->version + 1]);
            $p->events()->create(['actor_id' => $r->user()->id, 'status' => 'cancelled']);
        });

        return redirect()->route('payments.index')->with('success', 'Permintaan dibatalkan. Ini tidak memproses refund.');
    }

    public function admin()
    {
        return response()->view('payments.admin', ['payments' => SupportPayment::with('events')->latest()->paginate(15), 'enabled' => self::enabled()])->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $r, int $payment)
    {
        $d = $r->validate(['status' => ['required', Rule::in(['verified', 'declined'])], 'note' => 'required|string|min:20|max:1000', 'version' => 'required|integer|min:1', 'confirm' => 'accepted']);
        DB::transaction(function () use ($r, $payment, $d) {
            $p = SupportPayment::lockForUpdate()->findOrFail($payment);
            abort_unless($p->version === (int) $d['version'] && $p->status === 'pending', 409, 'Status berubah atau sudah diproses. Muat ulang.');
            $p->update(['status' => $d['status'], 'version' => $p->version + 1]);
            $p->events()->create(['actor_id' => $r->user()->id, 'status' => $d['status'], 'note' => $d['note']]);
        });

        return redirect()->route('admin.payments')->with('success','Hasil verifikasi manual dicatat.');
    }
}
