<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class JournalEvidenceExtractor
{
    public function extract(string $html, string $field): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//form') as $node) {
            $node->parentNode?->removeChild($node);
        }
        $pattern = match ($field) {
            'scope' => '/\b(scope|aims|focus|cakupan|ruang lingkup)\b/i',
            'fee' => '/\b(fee|charge|apc|biaya|gratis|free of charge)\b/i',
            'time' => '/\b(review|decision|waktu peninjauan|lama proses)\b/i',
            'acceptance' => '/\b(acceptance rate|rejection rate|tingkat penerimaan|tingkat penolakan)\b/i',
            'frequency' => '/\b(publish(?:ed|es)?|publication|issues?|terbit|diterbitkan)\b/i',
            'language' => '/\b(language|bahasa|manuscripts?|naskah)\b/i',
            default => null,
        };
        if ($pattern === null) {
            return [];
        }
        $quotes = [];
        foreach ($xpath->query('//body//p|//body//li') as $node) {
            $line = trim(preg_replace('/\s+/u', ' ', $node->textContent));
            $heading = $node->previousSibling;
            while ($heading && trim($heading->textContent) === '') {
                $heading = $heading->previousSibling;
            }
            // OJS content often wraps the paragraph in a div after its section heading.
            if ($field === 'scope' && (! $heading || ! preg_match('/^h[1-6]$/i', $heading->nodeName))) {
                $container = $node->parentNode;
                if ($container && $container->nodeName !== 'body') {
                    $heading = $container->previousSibling;
                    while ($heading && trim($heading->textContent) === '') {
                        $heading = $heading->previousSibling;
                    }
                }
            }
            $context = $line.' '.($heading && preg_match('/^h[1-6]$/i', $heading->nodeName) ? $heading->textContent : '');
            if (mb_strlen($line) < 20 || mb_strlen($line) > 600 || ! preg_match($pattern, $context)) {
                continue;
            }
            // OJS sometimes wraps a whole menu in a paragraph outside <nav>.
            if (preg_match_all('/\b(?:Editorial Team|Publication Ethics|Author Guidelines|Peer.Review Process|Indexing|Contact|Archives|Current Issue|Announcements)\b/iu', $line) >= 3) {
                continue;
            }
            if ($field === 'scope' && ! preg_match('/[.!?;:]/u', $line)) {
                continue;
            }
            if ($field === 'fee' && ! preg_match('/\b(?:free|gratis|no (?:publication |submission )?fee|tanpa biaya|\d[\d.,]*\s*(?:idr|usd|eur)|(?:rp|idr|usd|eur)\s*\d[\d.,]*)\b/iu', $line)) {
                continue;
            }
            if ($field === 'time' && ! preg_match('/\b\d+\s*(?:days?|weeks?|months?|hari|minggu|bulan)\b/iu', $line)) {
                continue;
            }
            if ($field === 'acceptance' && ! preg_match('/\d+(?:[.,]\d+)?\s*%/', $line)) {
                continue;
            }
            if ($field === 'frequency' && ! preg_match('/\b(?:two|three|four|six|dua|tiga|empat|enam|[2346])\s+(?:issues?|times?|nomor|kali)\s+(?:per|a|setiap|dalam)\s+(?:year|tahun)|\b(?:biannual|triannual|quarterly|bimonthly|dua|tiga|empat|enam)\b.{0,35}\b(?:year|tahun)\b/iu', $line)) {
                continue;
            }
            if ($field === 'language' && ! preg_match('/\b(?:English|Indonesian|Bahasa Indonesia|Inggris)\b/iu', $line)) {
                continue;
            }
            $quotes[$line] = $line;
            if (count($quotes) >= 3) {
                break;
            }
        }

        return array_values($quotes);
    }
}
