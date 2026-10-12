<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DinoikiEmbeddings
{
    public function vectors(array $texts): array
    {
        if (! config('semantic.enabled') || ! config('semantic.key')) {
            throw new RuntimeException('AI belum diaktifkan oleh pengelola. Gunakan pencocokan dasar.');
        }
        try {
            $r = Http::withToken(config('semantic.key'))->acceptJson()->connectTimeout(5)->timeout(20)
                ->withOptions(['allow_redirects' => false])->post(config('semantic.endpoint'), ['model' => config('semantic.model'), 'input' => $texts, 'encoding_format' => 'float']);
            if (! $r->successful()) {
                throw new RuntimeException;
            }
            $data = $r->json('data');
            if (! is_array($data) || count($data) !== count($texts)) {
                throw new RuntimeException;
            }
            $vectors = [];
            $dimension = null;
            foreach ($data as $item) {
                $i = $item['index'] ?? null;
                $v = $item['embedding'] ?? null;
                if (! is_int($i) || $i < 0 || $i >= count($texts) || isset($vectors[$i]) || ! is_array($v) || count($v) < 2 || count($v) > 4096) {
                    throw new RuntimeException;
                }
                foreach ($v as $number) {
                    if (! is_numeric($number) || ! is_finite((float) $number)) {
                        throw new RuntimeException;
                    }
                }
                $v = array_map('floatval', $v);
                $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));
                if ($norm <= 0 || ! is_finite($norm) || ($dimension !== null && $dimension !== count($v))) {
                    throw new RuntimeException;
                }
                $dimension = count($v);
                $vectors[$i] = array_map(fn ($x) => $x / $norm, $v);
            }
            ksort($vectors);

            return array_values($vectors);
        } catch (\Throwable $e) {
            // Never report provider bodies, token, manuscript input, or request objects.
            throw new RuntimeException('Layanan AI tidak tersedia atau respons tidak valid. Coba pencocokan dasar.');
        }
    }

    public function similarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new RuntimeException('Dimensi embedding tidak konsisten.');
        }

        return max(-1, min(1, array_sum(array_map(fn ($x, $y) => $x * $y, $a, $b))));
    }
}
