<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAccount
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()) {
            return $r->expectsJson() ? response()->json(['message' => 'Login diperlukan.'], 401) : redirect()->route('account.login');
        }

        if (config('account.email_enabled') && ! $r->user()->hasVerifiedEmail()) {
            return $r->expectsJson() ? response()->json(['message' => 'Verifikasi email diperlukan.'], 403) : redirect()->route('verification.notice');
        }

        return $next($r);
    }
}
