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

return $next($r);
    }
}
