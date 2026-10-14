# Publikasi current state

PROJECT: `arisbudianto/Publikasi` (Laravel 13), branch `main` canonical.
PRODUCTION: `https://publikasi.speakverse.id`; source `/home/speakver/publikasi_m00_source`; runtime `/home/speakver/publikasi.speakverse.id`.

## Verified 14 October 2026
- PR #25 merged at `15f39d0155c91bccccb050f0fcc6b0bcdc2ae420`. GitHub Actions run 38524425302 passed PHP 8.3 and 8.4 test jobs. M17 contextual journal entry, guide-page question form, and authenticated correction feedback were deployed to runtime; changed files compared, PHP lint, route/view caches and public guide, journal-detail and assistant-prefill pages checked.
- Five curated journals and 999 imported source records; zero verified no-APC entries, zero published M18 guides, and no Scopus discontinued snapshot. Editorial drafts are in `docs/m18/EDITORIAL_DRAFTS.md`, awaiting review and publication.
- M15–M19 additive migrations through batch 15 show Ran in production. Earlier backup archives were checked for contents/checksum; isolated database restore remains untested.
- Laravel schedules `m16:tick` every 15 minutes and `m19:tick` monthly. Existing cPanel cron was every eight hours; changed the sole `schedule:run` entry to every minute, with prior crontab saved at `/home/speakver/m17_completion_backup_20261014/crontab.before`. The installed crontab was checked. Automatic M16 run evidence has not yet been inspected.

## Verification still open
- M15 human relevance and 20-case cost pilot, M16 two automatic cron runs at least ten minutes apart and account flow, M19 source activation and candidate review, 500-journal M10 capacity test, authenticated M05–M09 flows, isolated restore: NOT VERIFIED.
- Admin review of three guide drafts and evidence-backed APC/journal curation remain open. Do not fabricate fees or indexing status. Obtain a current official Scopus discontinued snapshot, verify date/checksum, back up database and preview before any import.
- Production M16/M19 feature flags and cron heartbeat rows were not inspected in this audit; do not claim feature activation. Check them and perform bounded pilots with appropriate account/admin access.

## Guardrails
- Do not rerun migrations or historical baseline tests merely to update documentation. Never run `RefreshDatabase` tests against production.
- Preserve production `.env`, catalog and private data. No synthetic published journal or unreviewed guide.
