# Publikasi current state

PROJECT: `arisbudianto/Publikasi` (Laravel 13), branch `main` canonical.
PRODUCTION: `https://publikasi.speakverse.id`; source `/home/speakver/publikasi_m00_source`; runtime `/home/speakver/publikasi.speakverse.id`.

## Verified baseline
- `main` at `4a8ddd5586027ce407b0e9e4c148d6d87fd3d8b7` before the audit completion branch. M17 repair files were installed and compared in runtime on 14 October 2026; PHP syntax, Blade cache and assistant routes passed. Owner PDF showed deterministic scope and unknown fee answer for Jurnal Pendidikan Vokasi.
- Public pages on 14 October: curated catalog 5 journals, 999 imported sources; zero verified no-APC journals; zero published guides; no Scopus discontinued snapshot imported.
- M15 code and additive migration were previously installed. Earlier backup archives were checked for contents/checksum, but an isolated database restore was not tested.
- The previous state file described only M15 and was obsolete. M16–M19 code is present in `main`; production flags, cron heartbeats, source review and migrations have not been audited read-only here.

## Current objective
Close audit gaps without inventing journal facts. Add M17 contextual entry and feedback into M18 queue; prepare sourced editorial guide drafts. Keep data ingestion and activation behind their evidence gates.

## Verification
- M17 focused tests / full suite / CI: NOT VERIFIED for this branch until executed.
- Production deployment and changed-flow smoke for this branch: NOT VERIFIED until installed.
- M15 human relevance and 20-case cost pilot, M16 two automatic cron runs, M19 enabled source/candidate review, 500-journal M10 capacity test, authenticated M05–M09 flows, isolated restore: NOT VERIFIED.

## Open items
- Admin reviews and publishes `docs/m18/EDITORIAL_DRAFTS.md` through the guide UI. Drafts are not production facts.
- Obtain and preview current official Scopus discontinued snapshot, backup database, then import only with verified date and checksum.
- Curate source-backed APC and additional journal scopes; unknown must remain unknown until checked.
- Inspect cPanel migration/flag/cron status, then conduct bounded M15/M16/M19 pilot with account and admin access; do not claim activation from code alone.

## Do not redo
- Do not rerun migrations or historical baseline tests merely to update documentation. Never run `RefreshDatabase` tests against production.
- Preserve production `.env`, catalog and private data. No synthetic published journal or unreviewed guide.
