<?php

namespace App\Services;

use RuntimeException;

class JournalPageFetcher
{
    public function fetch(string $url): array
    {
        $host = $this->host($url);
        $robots = $this->request('https://'.$host.'/robots.txt', $host, 65536);
        if ($robots['status'] !== 200 && $robots['status'] !== 404 && $robots['status'] !== 410) {
            throw new RuntimeException('robots_unreachable');
        }
        if ($robots['status'] === 200 && ! $this->allowed($robots['body'], parse_url($url, PHP_URL_PATH) ?: '/')) {
            throw new RuntimeException('robots_disallowed');
        }

        $page = $this->request($url, $host, (int) config('m19.max_bytes'));
        if ($page['status'] !== 200) {
            throw new RuntimeException('page_http_'.$page['status']);
        }
        if (! str_contains(strtolower($page['content_type']), 'text/html')) {
            throw new RuntimeException('not_html');
        }

        return $page;
    }

    public function host(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])
            || strlen($url) > 1000 || ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/iD', $parts['host'])) {
            throw new RuntimeException('invalid_source_url');
        }

        return strtolower($parts['host']);
    }

    private function request(string $url, string $host, int $limit): array
    {
        if (! extension_loaded('curl')) {
            throw new RuntimeException('curl_unavailable');
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (! $records) {
            throw new RuntimeException('dns_unavailable');
        }
        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('unsafe_destination');
            }
            $ips[] = $ip;
        }
        $ip = $ips[0];
        $body = '';
        $type = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$host.':443:'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)],
            CURLOPT_USERAGENT => config('m19.user_agent'),
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Encoding: identity'],
            CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$type) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $type = trim(substr($header, 13));
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$body, $limit) {
                if (strlen($body) + strlen($chunk) > $limit) {
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($ok === false) {
            throw new RuntimeException('fetch_failed_or_oversized');
        }

        return ['status' => $status, 'body' => $body, 'content_type' => $type];
    }

    public function allowed(string $robots, string $path): bool
    {
        $groups = preg_split('/\R/', $robots);
        $active = false;
        $inRules = false;
        $best = -1;
        $allowed = true;
        foreach ($groups as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (preg_match('/^User-agent\s*:\s*(.+)$/i', $line, $m)) {
                if ($inRules) {
                    $active = false;
                    $inRules = false;
                }
                $active = $active || in_array(strtolower(trim($m[1])), ['*', 'publikasijournalmonitor', 'publikasijournalmonitor/1.0'], true);

                continue;
            }
            if (! preg_match('/^(Allow|Disallow)\s*:\s*(.*)$/i', $line, $m)) {
                continue;
            }
            $inRules = true;
            if (! $active || $m[2] === '') {
                continue;
            }
            $rule = trim($m[2]);
            $prefix = rtrim($rule, '$');
            $pattern = '~^'.str_replace('\\*', '.*', preg_quote($prefix, '~')).(str_ends_with($rule, '$') ? '$' : '').'~';
            if (preg_match($pattern, $path) && strlen($prefix) >= $best) {
                $best = strlen($prefix);
                $allowed = strtolower($m[1]) === 'allow';
            }
        }

        return $allowed;
    }
}
