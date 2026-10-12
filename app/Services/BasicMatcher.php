<?php

namespace App\Services;

use App\Models\Journal;
use Illuminate\Support\Str;

class BasicMatcher
{
    public const VERSION = 'm03-keywords-1';

    private const CONCEPTS = [
        'vokasi' => ['vokasi', 'vocational', 'kejuruan', 'technical'],
        'bahasa' => ['bahasa', 'language', 'linguistics', 'linguistik'],
        'sains' => ['sains', 'science', 'ipa'],
        'lingkungan' => ['lingkungan', 'environment', 'environmental'],
        'kurikulum' => ['kurikulum', 'curriculum'],
        'evaluasi' => ['evaluasi', 'evaluation', 'assessment', 'pengujian'],
        'teknologi' => ['teknologi', 'technology', 'digital', 'online'],
        'pembelajaran' => ['pembelajaran', 'learning', 'pengajaran', 'teaching'],
        'pelatihan' => ['pelatihan', 'training'],
    ];

    private const STOP = 'dan yang di ke dari untuk dengan pada dalam ini itu adalah sebagai oleh atau serta tentang terhadap melalui penelitian studi kajian hasil metode data analisis pengaruh hubungan penggunaan peningkatan pendidikan jurnal artikel empiris orisinal the a an and or of to in on for with this that is are was were study research results method methods analysis effect education educational journal original empirical';

    public function terms(string $text): array
    {
        $text = Str::lower(Str::ascii(strip_tags($text)));
        $tokens = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop = explode(' ', self::STOP);
        $out = [];
        foreach ($tokens as $token) {
            if (strlen($token) < 3 || ctype_digit($token) || in_array($token, $stop, true)) {
                continue;
            }
            foreach (self::CONCEPTS as $concept => $aliases) {
                if (in_array($token, $aliases, true)) {
                    $token = $concept;
                    break;
                }
            }
            $out[$token] = true;
        }

        return array_keys($out);
    }

    public function match(string $title, string $abstract, string $context): array
    {
        $titleTerms = $this->terms($title);
        $abstractTerms = $this->terms($abstract);
        $contextTerms = $this->terms($context);
        $results = [];
        // Process in bounded chunks; only retain the ten best candidates.
        Journal::where('status', 'published')->orderBy('id')->chunkById(100, function ($journals) use (&$results, $titleTerms, $abstractTerms, $contextTerms, $context) {
            foreach ($journals as $journal) {
                // Journal name and publisher are deliberately excluded as scope evidence.
                $scopeTerms = $this->terms($journal->subject.' '.$journal->scope_summary);
                $titleMatches = array_values(array_intersect($titleTerms, $scopeTerms));
                $abstractMatches = array_values(array_intersect($abstractTerms, $scopeTerms));
                $contextMatches = array_values(array_intersect($contextTerms, $scopeTerms));
                if (! $titleMatches && ! $abstractMatches) {
                    continue;
                }
                $results[] = ['journal' => $journal, 'title_matches' => $titleMatches, 'abstract_matches' => $abstractMatches,
                    'context_matches' => $contextMatches, 'context_checked' => trim($context) !== '',
                    'score' => count($titleMatches) * 3 + count($abstractMatches) + count($contextMatches)];
            }
            usort($results, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['journal']->id <=> $b['journal']->id));
            $results = array_slice($results, 0, 10);
        });

        return $results;
    }
}
