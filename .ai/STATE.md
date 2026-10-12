# Publikasi current state

PROJECT arisbudianto/Publikasi main. Production source-data release `1fec710`; all-disciplines directory release `6e0aa10`. Snapshot dated 2025-03-20. Production app `/home/speakver/publikasi.speakverse.id`; deployment source `/home/speakver/publikasi_m00_source`.

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

## Not verified / limits
- `publikasi:health`, private authenticated production writes, database backup/restore, and rollback rehearsal were not run.
- Snapshot source is dated 2025-03-20; category labels, ISSNs, SINTA ranks, URLs and article titles require current source verification before promotion. Raw archive reported one failed profile (ID664); unnamed records are stored but excluded from public listing.
- Rollback must retain imported `journal_sources` data; do not rollback/drop migration after import.

See `docs/data/SINTA_IMPORT.md` and `docs/data/DEPLOYMENT_EVIDENCE.md`. Preserve production `.env` and secrets. Never run production PHPUnit/RefreshDatabase or `provision-cpanel.php`.
