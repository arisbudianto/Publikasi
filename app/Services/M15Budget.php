<?php

namespace App\Services;

use App\Models\M15AiUsage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class M15Budget
{
    public function reserve(float $amount): float
    {
        $amount = round(max(0, $amount), 6);
        $period = now('UTC')->format('Y-m');

        return DB::transaction(function () use ($amount, $period) {
            DB::table('m15_ai_usage')->insertOrIgnore([
                'period' => $period,
                'spent_usd' => 0,
                'reserved_usd' => 0,
                'request_count' => 0,
                'analysis_count' => 0,
                'openalex_requests' => 0,
                'openalex_cost_usd' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $usage = M15AiUsage::query()->where('period', $period)->lockForUpdate()->firstOrFail();
            $available = (float) config('m15.monthly_budget_usd', 5);
            if ((float) $usage->spent_usd + (float) $usage->reserved_usd + $amount > $available) {
                throw new RuntimeException('Batas biaya analisis bulanan telah tercapai. Coba lagi bulan depan.');
            }

            $usage->increment('reserved_usd', $amount);

            return $amount;
        });
    }

    public function settle(float $reservation, int $inputTokens, int $outputTokens, float $actualCost): void
    {
        $this->adjust($reservation, $inputTokens, $outputTokens, $actualCost, true);
    }

    public function release(float $reservation): void
    {
        $this->adjust($reservation, 0, 0, 0, false);
    }

    public function snapshot(): array
    {
        $usage = M15AiUsage::query()->where('period', now('UTC')->format('Y-m'))->first();

        return [
            'spent_usd' => (float) ($usage?->spent_usd ?? 0),
            'reserved_usd' => (float) ($usage?->reserved_usd ?? 0),
            'request_count' => (int) ($usage?->request_count ?? 0),
            'analysis_count' => (int) ($usage?->analysis_count ?? 0),
            'openalex_requests' => (int) ($usage?->openalex_requests ?? 0),
            'openalex_cost_usd' => (float) ($usage?->openalex_cost_usd ?? 0),
        ];
    }

    public function recordOpenAlex(float $reportedCost): void
    {
        $period = now('UTC')->format('Y-m');
        DB::transaction(function () use ($period, $reportedCost) {
            DB::table('m15_ai_usage')->insertOrIgnore([
                'period' => $period,
                'spent_usd' => 0,
                'reserved_usd' => 0,
                'request_count' => 0,
                'analysis_count' => 0,
                'openalex_requests' => 0,
                'openalex_cost_usd' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $usage = M15AiUsage::query()->where('period', $period)->lockForUpdate()->firstOrFail();
            $usage->openalex_requests++;
            $usage->openalex_cost_usd = round((float) $usage->openalex_cost_usd + max(0, $reportedCost), 6);
            $usage->save();
        });
    }

    public function claimAnalysis(): void
    {
        $period = now('UTC')->format('Y-m');
        DB::transaction(function () use ($period) {
            DB::table('m15_ai_usage')->insertOrIgnore([
                'period' => $period,
                'spent_usd' => 0,
                'reserved_usd' => 0,
                'request_count' => 0,
                'analysis_count' => 0,
                'openalex_requests' => 0,
                'openalex_cost_usd' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $usage = M15AiUsage::query()->where('period', $period)->lockForUpdate()->firstOrFail();
            if ($usage->analysis_count >= (int) config('m15.monthly_analysis_limit', 20)) {
                throw new RuntimeException('Batas analisis pilot bulan ini telah tercapai.');
            }
            $usage->increment('analysis_count');
        });
    }

    private function adjust(float $reservation, int $inputTokens, int $outputTokens, float $actualCost, bool $settle): void
    {
        $period = now('UTC')->format('Y-m');
        DB::transaction(function () use ($period, $reservation, $inputTokens, $outputTokens, $actualCost, $settle) {
            $usage = M15AiUsage::query()->where('period', $period)->lockForUpdate()->first();
            if (! $usage) {
                return;
            }
            $usage->reserved_usd = max(0, (float) $usage->reserved_usd - $reservation);
            if ($settle) {
                $usage->spent_usd = round((float) $usage->spent_usd + max(0, $actualCost), 6);
                $usage->request_count++;
                $usage->input_tokens += max(0, $inputTokens);
                $usage->output_tokens += max(0, $outputTokens);
            }
            $usage->save();
        });
    }
}
