<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAccountController extends Controller
{
    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(Request $request)
    {
        abort_unless($this->configured(), 503, 'Login Google belum tersedia.');

        $state = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put('google_oauth', [
            'state' => $state,
            'verifier' => $verifier,
            'started' => time(),
            'link_user' => $request->user()?->id,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('account.google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request)
    {
        abort_unless($this->configured(), 503);
        $pending = $request->session()->pull('google_oauth');
        if (! is_array($pending) || ! is_string($request->query('state')) || ! hash_equals($pending['state'] ?? '', $request->query('state')) || time() - ($pending['started'] ?? 0) > 600 || ! is_string($request->query('code')) || strlen($request->query('code')) > 2048) {
            return redirect()->route('account.login')->with('account_error', 'Login Google tidak valid atau kedaluwarsa. Coba lagi.');
        }

        try {
            $token = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => route('account.google.callback'),
                'grant_type' => 'authorization_code',
                'code_verifier' => $pending['verifier'],
            ])->throw()->json();
            if (! is_string($token['access_token'] ?? null)) {
                throw new \RuntimeException('Missing Google access token');
            }
            $profile = Http::withToken($token['access_token'])->timeout(10)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (\Throwable) {
            return redirect()->route('account.login')->with('account_error', 'Google tidak dapat memverifikasi akun saat ini. Coba lagi.');
        }

        $sub = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        if (! is_string($sub) || $sub === '' || strlen($sub) > 255 || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || ($profile['email_verified'] ?? false) !== true) {
            return redirect()->route('account.login')->with('account_error', 'Akun Google tidak menyediakan alamat email terverifikasi.');
        }
        $email = Str::lower($email);
        $existing = User::where('google_sub', $sub)->first();
        $linkUser = $pending['link_user'] ?? null;
        if ($linkUser !== null) {
            if (! $request->user() || $request->user()->id !== $linkUser || $request->user()->is_admin || ($existing && $existing->id !== $linkUser) || ($request->user()->google_sub && $request->user()->google_sub !== $sub)) {
                abort(403);
            }
            $request->user()->forceFill(['google_sub' => $sub])->save();

            return redirect()->route('account.index')->with('success', 'Akun Google telah terhubung.');
        }
        if ($existing) {
            if ($existing->is_admin) {
                abort(403);
            }
            Auth::login($existing);
        } else {
            if (User::where('email', $email)->exists()) {
                return redirect()->route('account.login')->with('account_error', 'Email ini sudah terdaftar. Masuk dengan password, lalu hubungkan Google dari halaman akun.');
            }
            $authoritative = str_ends_with($email, '@gmail.com') || filled($profile['hd'] ?? null);
            if (! $authoritative && ! config('account.email_enabled')) {
                return redirect()->route('account.login')->with('account_error', 'Verifikasi email tambahan belum tersedia untuk alamat ini.');
            }
            try {
                $user = DB::transaction(function () use ($profile, $email, $sub, $authoritative) {
                    $user = User::create([
                        'name' => Str::limit(filled($profile['name'] ?? null) ? (string) $profile['name'] : Str::before($email, '@'), 100, ''),
                        'email' => $email,
                        'password' => Str::random(64),
                    ]);
                    $user->forceFill(['google_sub' => $sub, 'email_verified_at' => $authoritative ? now() : null, 'is_admin' => false])->save();

                    return $user;
                });
            } catch (QueryException) {
                return redirect()->route('account.login')->with('account_error', 'Akun sudah terdaftar. Coba masuk kembali.');
            }
            Auth::login($user);
            if (! $authoritative) {
                $user->sendEmailVerificationNotification();
            }
        }

        $request->session()->regenerate();

        return redirect()->route(config('account.email_enabled') && ! Auth::user()->hasVerifiedEmail() ? 'verification.notice' : 'account.index');
    }
}
