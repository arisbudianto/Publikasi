<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class JournalScopeLinkFinder
{
    public function __construct(private JournalPageFetcher $fetcher) {}

    public function find(string $html, string $baseUrl): ?string
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $baseHost = $this->fetcher->host($baseUrl);
        foreach ((new DOMXPath($doc))->query('//a[@href]') as $anchor) {
            if (! preg_match('/\b(focus\s*(?:and|&)\s*scope|aims?\s*(?:and|&)\s*scope|ruang lingkup|cakupan jurnal)\b/iu',
                trim($anchor->textContent))) {
                continue;
            }
            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '//') || str_contains($href, '#')) {
                continue;
            }
            $url = str_starts_with($href, 'https://') ? $href
                : (str_starts_with($href, '/') ? 'https://'.$baseHost.$href
                    : (preg_match('/^[a-z][a-z\d+.-]*:/i', $href) ? ''
                        : 'https://'.$baseHost.rtrim(dirname(parse_url($baseUrl, PHP_URL_PATH) ?: '/'), '/').'/'.$href));
            try {
                if ($url !== '' && $url !== $baseUrl && $this->fetcher->host($url) === $baseHost) {
                    return $url;
                }
            } catch (\RuntimeException) {
                continue;
            }
        }

        return null;
    }
}
