# M06 deployment evidence

2026-10-12 Asia/Jakarta. Application commit632ffd209da8bba4f8a115bd2c0e5dd0b4cc9f03.
PASS: preflight source clean cfeea5f; nine replaced existing files matched. Observation rows0, retired_at absent. Backup /home/speakver/publikasi_backups/pre-m06-20261012.tar.gz nine source files; archive listing verified. No credential/config/private data overwrite.
PASS: targeted migration2026_10_12_000003 ran on MariaDB (nullable fields/index/reviewer FK), no row backfill or deletion. Autoload/route/view caches rebuilt, app up. Thirteen changed application files matched source.
PASS: HTTPS /,/jurnal/2,/cocokkan,/cocokkan-ai200; /admin/kurasi without login302 to /admin/login. Browser public detail identity and unknown fields render. Final observation rows0/active0; no synthetic production observations created. Dinoiki key-present/enabled YES checked without reading key.
PASS local46 tests/307 assertions: curation authorization, version/audit persistence, active-only public observations/detail/compare/known-filter, withdrawal, invalid date/source/note/confirmation, foreign-journal IDOR, freshness, scoped article refresh/audit and M01-M05 regressions.
OWNER REPORT: user confirms admin login and menus can be tried. Independent authenticated production publish/retire workflow NOT VERIFIED; no password was requested again after secure dialog issues. MariaDB concurrent edit race and automated external verification NOT VERIFIED. Manual verification source rules retained; no cron/scraping deployed.
Rollback restore nine replaced files and rebuild route/view/autoload caches, leave additive DB columns/history intact. Dropping review columns would destroy history and is not authorized.
