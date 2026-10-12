# M01 deployment evidence

Verified 2026-10-12 (Asia/Jakarta).

- Application commit e56bdfd341c0284140bf5bbb8475af7fee86c314 installed in /home/speakver/publikasi.speakverse.id; document root public/.
- PHP CLI 8.4.26, MariaDB hosting via Laravel mysql driver. Database and user: speakver_publikasi (created by owner). No credentials committed.
- Owner completed migrations, PilotJournalSeeder and admin creation; server read-only count confirmed 5 journals and 1 admin.
- Config, route and Blade view caches succeeded; artisan up succeeded.
- HTTPS /, /up and /admin/login: HTTP 200.
- /admin without session: HTTP 302 to /admin/login.
- /.env: HTTP 403; /composer.json and /docs/m00/BLUEPRINT.md: HTTP 404.
- Browser search ISSN 0216-1370 returned one journal. Journal detail rendered official scope source, check date and unknown publication metadata.
- Local application verification: 10 tests, 44 assertions passed.
- Live admin login and live CRUD were not tested with owner password; automated admin authorization/CRUD/import tests passed locally.
- Pre-M01 filesystem backup: /home/speakver/publikasi_backups/pre-m01-20261012.tar.gz; archive listing verified.

The earlier provision-cpanel.php targets a different database user and must not be rerun on this configured deployment. Use artisan publikasi:admin only when intentionally creating a new admin. Never run RefreshDatabase tests against production.
