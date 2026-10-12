# M08/M09 deployment evidence, 2026-10-12

Application commit `df62fad8a76114f8a392b7221843f3ec9ec54812`, base `81278a3b2013cf3dbab8d92ebe197fe1543ce920`, repository arisbudianto/Publikasi main.

## Verified local

- Full regression suite:61 tests/450 assertions PASS, isolated SQLite/PHP8.3. Final M08 pagination/search adjustment:targeted5 tests/54 assertions PASS; unrelated full-suite proof reused.
- Scoped Pint, diff check, Laravel route:cache and view:cache PASS.
- Ownership for submission/matrix/export (including admin), transition and date rules, addressed response requirements, stale409, CSV formula neutralization; ledger disabled503, integer amounts, consent, duplicate pending409, owner cancel, admin confirmation and replay409 covered.
- Health failures return safe status rather than exception/secret values. No real payment/provider request or production PHPUnit executed.

## Verified cPanel

Source clean at base81278a3. Five existing runtime files matched old source before replacement. New tables were absent. Scoped backup `/home/speakver/publikasi_backups/pre-m08-m09-20261012.tar.gz` listed the five files; restore not exercised. Source fast-forwarded to application SHA.

Migration000005 DONE145.24ms; migration000006 DONE88.86ms. These added five tables without modifying existing rows. Existing users1/journals5 remained1/5. New submission/reviewer/payment/event tables had zero rows after installation; submissions/payments remained0 after anonymous smoke.

Runtime archive installed scoped files, optimized autoload, config cache (new payments config), route cache and view cache; application up observed. `.env` and credentials were not overwritten. Thirty deployed files matched source; `.env.example` intentionally not installed. Source clean after deployment.

`php artisan publikasi:health --json` reported healthy=true, all checks PASS: production environment/debug off/key present/HTTPS/storage writable/DB/schema/cache/AI key ready/no failed or queued jobs. queued_jobs0/failed_jobs0; payments_enabled=false; ai_enabled=true and ai_key_set=true. Key values were not read. This probe does not call Dinoiki.

Fresh anonymous HTTPS session with CSRF token: POST /akun/submission401; POST /akun/pembayaran401; GET /akun/submission401; GET /admin/operasional401; GET /admin/pembayaran401. Public /layanan200 with Layanan Publikasi identity. Browser title/marker M09 and nonactive payment text independently observed.

## Not verified / limits

Authenticated production create/update submission, matrix/export, admin web diagnostics and payment verification were not independently exercised. No test account, private submission or payment data was created. Physical mobile layout and simultaneous MariaDB writers not exercised. Local boundary tests do not replace authenticated production smoke.

No live gateway, charges, refund, SMTP, cron/worker setup, external monitoring, retention automation, full database backup or isolated restore test. M09 is implemented diagnostic/runbook/disabled manual-ledger scope, not a claim of complete production operations or financial readiness. A payment channel/provider and approved policies are still needed before activation.

Code rollback retains new tables/data; restore five existing files and rebuild config/route/view caches from old code. Do not destructively roll back migrations as part of routine code rollback.
