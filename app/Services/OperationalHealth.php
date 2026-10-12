<?php

namespace App\Services;

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class OperationalHealth
{
    public function inspect(): array
    {
        $checks = [
            'environment' => app()->environment('production') ? 'PASS' : 'FAIL',
            'debug_off' => config('app.debug') === false ? 'PASS' : 'FAIL',
            'app_key_set' => filled(config('app.key')) ? 'PASS' : 'FAIL',
            'https_url' => str_starts_with((string) config('app.url'), 'https://') ? 'PASS' : 'FAIL',
            'storage_writable' => is_writable(storage_path('framework/views')) && is_writable(storage_path('framework/sessions')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache')) ? 'PASS' : 'FAIL',
        ];
        try {
            DB::select('select 1');
            $checks['database'] = 'PASS';
            $tables = ['users', 'journals', 'submission_checklists', 'submissions', 'submission_events', 'reviewer_responses', 'support_payments', 'support_payment_events', 'jobs', 'failed_jobs'];
            $checks['schema'] = collect($tables)->every(fn ($t) => Schema::hasTable($t)) ? 'PASS' : 'FAIL';
            $counts = ['queued_jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count()];
        } catch (Throwable) {
            $checks['database'] = 'FAIL';
            $checks['schema'] = 'FAIL';
            $counts = ['queued_jobs' => null, 'failed_jobs' => null];
        }
        $key = 'publikasi-health:'.Str::uuid();
        try {
            Cache::put($key, 'ok', 60);
            $checks['cache'] = Cache::get($key) === 'ok' ? 'PASS' : 'FAIL';
        } catch (Throwable) {
            $checks['cache'] = 'FAIL';
        } finally {
            try {
                Cache::forget($key);
            } catch (Throwable) {
            }
        }
        $checks['ai_ready'] = ! config('semantic.enabled') || filled(config('semantic.key')) ? 'PASS' : 'FAIL';
        $checks['failed_jobs_empty'] = $counts['failed_jobs'] === 0 ? 'PASS' : 'WARN';
        $checks['queue_idle'] = $counts['queued_jobs'] === 0 ? 'PASS' : 'WARN';

        return ['checked_at' => now()->utc()->toIso8601String(), 'healthy' => ! in_array('FAIL', $checks, true), 'checks' => $checks, 'counts' => $counts, 'payments_enabled' => PaymentController::enabled(), 'ai_enabled' => (bool) config('semantic.enabled'), 'ai_key_set' => filled(config('semantic.key'))];
    }
}
