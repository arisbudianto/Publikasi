# Publikasi current state

PROJECT arisbudianto/Publikasi main. Production source-data release `1fec710`; all-disciplines directory release `6e0aa10`; SINTA accreditation release `4dcfa5d1d173653bbe58d58b66ec137606b214af`; SINTA 1–4 categories release `20e501ac8d9e7dc7faa80bd2e90e7060148e3ee2`. Production app `/home/speakver/publikasi.speakverse.id`; deployment source `/home/speakver/publikasi_m00_source`.

## Verified
- Local suite after all-disciplines changes: 66 tests / 506 assertions PASS (PHP 8.3, isolated SQLite); Pint, route/view cache and diff checks PASS.
- Production source fast-forwarded from `918fdc0` to `1fec710`; clean status reported.
- Scoped file backup: `/home/speakver/publikasi_backups/pre-sinta-20261012-1648.tar.gz`, SHA-256 `ef3a890e61e5a805c9e8722e0cf224c868dc9f59b78f626e5638392f6c20c9be`.
- Migration `000007` applied; table `journal_sources` created empty before import.
- Production dry run: 999 total, 308 Education, 75 unnamed, 999 new. Import created 999; replay created 0 / skipped 999. Final counts: sources999, education308, unnamed75, journals5, users1.
- Production route list registered public index/detail and admin prepare/promote routes. Route and Blade caches rebuilt; app returned from maintenance mode.
- Public smoke verified `/sumber-jurnal` lists 308 records, ISSN search `24423904` returns Jurnal Pendidikan Sains, and detail `/sumber-jurnal/1` shows source metadata and article-title snapshot.
- All-disciplines release fast-forwarded to production source `6e0aa10`; six changed runtime files backed up at `/home/speakver/publikasi_backups/pre-sinta-all-fields-20261012-1710.tar.gz`, SHA-256 `0cb271aa4608a846ba6ec6a6dcdfcb1ceba30aeefda1225380637b638c4df549`. Controller and Blade code deployed; Blade cache rebuilt. No schema/data mutation.
- Production counts after this release remain sources999 (education308, other691, unnamed75), journals5. Homepage identifies both the five curated catalog journals and the 999-source directory. `/sumber-jurnal` shows 999, all subjects; exact SINTA ID search `?q=15` displays the unnamed record.
- Import does not modify curated `journals`; data remains historical and outside matchers until editorial verification. No live accreditation or license is asserted.
- SINTA decision release `4dcfa5d1d173653bbe58d58b66ec137606b214af` deployed. Runtime backup `/home/speakver/publikasi_backups/pre-accreditation-20261012-180457.tar.gz`, SHA-256 `d3206b7cc3c1109c0624f3cedc4a71990e9fa3bf05923a3f55dd0f265168d8df`; owner confirmed database export from phpMyAdmin before migration (file/checksum not recorded). Migration `000008` applied. Production totals: accreditation2697; source links83; curated-journal links0; match statuses matched83, unmatched2592, invalid ISSN18, missing ISSN4; prior source999, curated journals5, users1 unchanged. Public `/akreditasi-sinta` lists 2697 decisions; exact ISSN `23556544` returns Geoplanning linked to source `/sumber-jurnal/932`.
- Category release `20e501ac8d9e7dc7faa80bd2e90e7060148e3ee2` deployed. Backup `/home/speakver/publikasi_backups/pre-sinta-categories-20261012-182246.tar.gz`, SHA-256 `0224508481c0bd988619b75fdf7b2f890e5f6887386349b948de42a50faa45fd`. Production cards show SINTA 1=28, 2=192, 3=658, 4=1,130 decisions; rank4 filter returned 1,130. Ranks5–6 remain available in full filter. No database mutation.
- Local full suite after category changes: 69 tests / 563 assertions PASS; Pint and route/view cache checks PASS. Full details in `docs/data/DEPLOYMENT_EVIDENCE.md`.

## Not verified / limits
- `publikasi:health`, private authenticated production writes, database backup/restore, and rollback rehearsal were not run.
- Snapshot source is dated 2025-03-20; category labels, ISSNs, SINTA ranks, URLs and article titles require current source verification before promotion. Raw archive reported one failed profile (ID664); unnamed records are stored but excluded from public listing.
- Rollback must retain imported `journal_sources` data; do not rollback/drop migration after import.

See `docs/data/SINTA_IMPORT.md` and `docs/data/DEPLOYMENT_EVIDENCE.md`. Preserve production `.env` and secrets. Never run production PHPUnit/RefreshDatabase or `provision-cpanel.php`.
