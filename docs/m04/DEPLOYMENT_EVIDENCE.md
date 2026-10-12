# M04 deployment evidence

2026-10-12 Asia/Jakarta. App commit 52abb4e36ddc4d46aa1ce02a67fe7536b40c7c22.
PASS: preflight source clean df296e5; three replaced files matched. Backup /home/speakver/publikasi_backups/pre-m04-20261012.tar.gz contains routes/web.php, public/catalog.css, resources/views/layout.blade.php (archive listing verified).
PASS: source pull, selective archive extraction, production autoload, config/route/view caches succeeded. No DB schema/data/credential changes.
PASS: browser /cocokkan-ai app identity and disabled-state consent form. HTTPS GET200, POST without consent422, valid-input POST while disabled503.
PASS: real Crossref retrieval for ISSN2088-2866 returned status available, three matching article metadata records. Only public ISSN sent.
PASS: local29 tests /178 assertions using mocked AI/HTTP incl redaction, malformed vectors, consent, limits, private exclusion, metadata validation, failures, regressions.
NOT VERIFIED: live Dinoiki because owner API key not configured, complete live semantic/article ranking, quality benchmark, provider retention. M04 code deployed; activation pending key handoff. Do not label live AI complete.
Owner setup in /home/speakver/publikasi.speakverse.id/.env: SEMANTIC_AI_ENABLED=true and DINOIKI_API_KEY filled privately; php artisan config:cache. Only then run a synthetic live smoke with UI consent. Costs debit owner Dinoiki account; app bounds requests but does not guarantee monetary budget.
