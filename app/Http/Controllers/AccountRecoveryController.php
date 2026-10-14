<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountRecoveryController extends Controller
{
    public function forgot()
    {
        return response()->view('account.forgot')->header('Cache-Control', 'no-store, private');
    }

    public function sendReset(Request $request)
    {
        abort_unless(config('account.email_enabled'), 503);
        $data = $request->validate(['email' => 'required|email|max:255']);
        Password::sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return back()->with('success', 'Jika email terdaftar, tautan pemulihan akan dikirim. Periksa juga folder spam.');
    }

    public function resetForm(string $token, Request $request)
    {
        abort_unless(config('account.email_enabled'), 503);

        return response()->view('account.reset', ['token' => $token, 'email' => $request->query('email')])->header('Cache-Control', 'no-store, private');
    }

    public function reset(Request $request)
    {
        abort_unless(config('account.email_enabled'), 503);
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email|max:255', 'password' => 'required|string|min:12|max:128|confirmed']);
        $data['email'] = Str::lower(trim($data['email']));
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Tautan tidak berlaku atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        return redirect()->route('account.login')->with('success', 'Password diperbarui. Silakan masuk.');
    }

    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        return response()->view('account.verify')->header('Cache-Control', 'no-store, private');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('account.index')->with('success', 'Email berhasil diverifikasi.');
    }

    public function resend(Request $request)
    {
        abort_unless(config('account.email_enabled'), 503);
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Jika belum terverifikasi, tautan baru telah dikirim.');
    }
}
