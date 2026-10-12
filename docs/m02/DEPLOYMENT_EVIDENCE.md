# M02 deployment evidence

2026-10-12 Asia/Jakarta. Application commit 9b859eb3009193d7ded8466a2280c9d5feb779a5.

PASS: preflight source checkout clean at 440bbac; four existing changed production files matched source before deployment. Five application files match source after deployment. Target /home/speakver/publikasi.speakverse.id, document root public/.
PASS: route:cache and view:cache; no migration, seed, dependency or credential change.
PASS: HTTPS three-journal comparison 200; four IDs with JSON Accept rejected 422; missing journal 404; fee-availability filter 200 and empty result (no fee data), science subject filter returns Jurnal Pendidikan IPA Indonesia only. Browser table shows three journals, provenance dates and unknown metadata.
PASS: local 16 tests / 93 assertions, including selection persistence/pagination, combined filters, duplicate IDs, private lifecycle exclusion and XSS escaping.
NOT VERIFIED: live pagination beyond pilot five journals, mobile physical device, Safari. Browser automation click observations were interrupted by credential protection; endpoint rendering verified by direct GET and local feature tests.
Backup: /home/speakver/publikasi_backups/pre-m02-20261012.tar.gz contains four replaced M01 files. Rollback restore those files and rebuild route/view caches; additional compare template may remain unused. Database unchanged.
