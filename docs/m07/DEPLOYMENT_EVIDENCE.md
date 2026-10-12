# M07 deployment evidence — 2026-10-12

Application commit: `26a265a53aa56878b6266ac44b68518004fca1ae` on GitHub main.

- Local full regression: 50 tests, 335 assertions PASS (isolated SQLite; PHP8.3). Ownership, validation, archived read-only behavior and stale-version409 covered.
- cPanel source clean before fast-forward pull. Four existing runtime files matched deployment before replacement.
- Scoped backup: `/home/speakver/publikasi_backups/pre-m07-20261012.tar.gz`; archive listing inspected. Restore was not exercised.
- Additive migration `2026_10_12_000004_create_submission_checklists.php` completed on production MariaDB. Existing tables were not replaced.
- Application files installed; autoload, route and view caches rebuilt; application returned online.
- Public `/jurnal/2/checklist` rendered ten items, progress0/10, official journal link and disabled guest save control.
- Fresh anonymous HTTPS request with CSRF session: POST `/akun/checklist/2` returned401. Production checklist count remained0.
- Seven installed runtime files compared identical to source: ChecklistController, AccountController, SubmissionChecklist, routes/web.php, checklist/show, catalog/show and account/index.

Authenticated save in production has not been independently verified: no test account or private checklist was created. Physical mobile layout and concurrent production writes were not exercised. Local automated checks do not establish production authenticated behavior. Checklist progress is a personal assessment, not editorial verification or an acceptance probability.

Rollback code separately from data: retain the additive checklist table and user data when reverting application files. Do not run destructive migration rollback as part of routine code rollback. Environment credentials were not overwritten.
