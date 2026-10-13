<?php

namespace App\Services;

class ReferenceTextParser
{
    public function split(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [];
        }

        $lines = explode("\n", $text);
        $numbered = collect($lines)->contains(fn ($line) => preg_match('/^\s*(?:\[\d+\]|\d{1,3}[.)])\s+/u', $line) === 1);
        $entries = [];
        $current = '';

        foreach ($lines as $line) {
            $isNumberedStart = preg_match('/^\s*(?:\[\d+\]|\d{1,3}[.)])\s+(.*)$/u', $line, $match) === 1;

            if ($numbered && $isNumberedStart) {
                if (trim($current) !== '') {
                    $entries[] = $this->clean($current);
                }
                $current = $match[1];
                continue;
            }

            if (trim($line) === '') {
                if (trim($current) !== '') {
                    $entries[] = $this->clean($current);
                    $current = '';
                }
                continue;
            }

            if ($current === '') {
                $current = trim($line);
            } elseif ($numbered || preg_match('/^\s/u', $line) === 1) {
                $current .= ' '.$this->clean($line);
            } else {
                $entries[] = $this->clean($current);
                $current = trim($line);
            }
        }

        if (trim($current) !== '') {
            $entries[] = $this->clean($current);
        }

        return array_values(array_filter($entries, fn ($entry) => $entry !== ''));
    }

    public function fields(string $reference): array
    {
        $doi = null;
        if (preg_match('~(?:https?://(?:dx\.)?doi\.org/|doi\s*:\s*)?(10\.\d{4,9}/[^\s<>"\]]+)~i', $reference, $match) === 1) {
            $doi = rtrim($match[1], '.,;');
            if (preg_match('~^10\.\d{4,9}/[^\s<>"\]]+$~i', $doi) !== 1) {
                $doi = null;
            }
        }

        $year = preg_match('/\b((?:19|20)\d{2})\b/u', $reference, $yearMatch) === 1
            ? (int) $yearMatch[1]
            : null;

        $title = null;
        $authorPrefix = null;
        if (preg_match('/^(.*?)\s*(?:\(((?:19|20)\d{2})[a-z]?\)|\b((?:19|20)\d{2})\b)\.?\s+(.+)$/iu', trim($reference), $parts) === 1) {
            $authorPrefix = trim($parts[1], " \t,.;");
            $tail = trim($parts[4]);
            $titleParts = preg_split('/\.\s+(?=[\p{Lu}\p{N}"“])/u', $tail, 2);
            $candidate = trim($titleParts[0] ?? '', " \t\n\r\0\x0B.");
            if ($candidate !== '') {
                $title = $candidate;
            }
        }

        $firstAuthor = null;
        if (is_string($authorPrefix) && $authorPrefix !== '') {
            $firstAuthor = trim(explode(',', $authorPrefix, 2)[0], " \t,.;");
            $firstAuthor = preg_replace('/\s+(?:&|and)\s+.*$/iu', '', $firstAuthor) ?: $firstAuthor;
        }

        return ['doi' => $doi, 'year' => $year, 'title' => $title, 'first_author' => $firstAuthor];
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
