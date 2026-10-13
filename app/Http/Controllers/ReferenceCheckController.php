<?php

namespace App\Http\Controllers;

use App\Services\CrossrefReferenceChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ReferenceCheckController extends Controller
{
    public function index()
    {
        return response()->view('references.index')->header('Cache-Control', 'no-store, private');
    }

    public function lookup(Request $request, CrossrefReferenceChecker $checker): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reference' => ['required', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid.', 'errors' => $validator->errors()], 422)
                ->header('Cache-Control', 'no-store, private');
        }

        $identity = $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.hash('sha256', (string) $request->ip());
        $userKey = 'reference-check:user:'.hash('sha256', $identity);
        $globalKey = 'reference-check:global:'.now()->format('Y-m-d');
        $userLimit = max(1, (int) config('references.user_daily_limit', 200));
        $globalLimit = max(1, (int) config('references.global_daily_limit', 1000));

        if (RateLimiter::tooManyAttempts($userKey, $userLimit)
            || RateLimiter::tooManyAttempts($globalKey, $globalLimit)) {
            return response()->json(['message' => 'Batas pemeriksaan sementara tercapai. Coba lagi nanti.'], 429)
                ->header('Cache-Control', 'no-store, private');
        }

        RateLimiter::hit($userKey, now()->endOfDay()->diffInSeconds(now()));
        RateLimiter::hit($globalKey, now()->endOfDay()->diffInSeconds(now()));

        $result = $checker->check($validator->validated()['reference']);

        return response()->json($result)->header('Cache-Control', 'no-store, private');
    }
}
