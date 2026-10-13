# Publikasi current state

PROJECT `arisbudianto/Publikasi`; default branch `main`; Laravel 13; production app `https://publikasi.speakverse.id`; cPanel source `/home/speakver/publikasi_m00_source`; app path `/home/speakver/publikasi.speakverse.id`.

## Verified production state
- M13 deployed at commit `901ebc32ac30c2aaf4ac17160efac28a6c6c3f87`.
- M13 backup: `/home/speakver/publikasi_backups/pre-m13-20261013-071607.tar.gz`, SHA-256 `fc4ed61ec8f4e130f04302d7f962495592de91659bc47a43612dc9f974a857b7`.
- M13 route cache and Blade cache rebuilt. `/cek-referensi` and `/reference-check.js` both returned HTTP 200; routes were registered.
- Existing catalogue data were not changed by M13. Previously verified production totals: 999 source records, 2,697 SINTA accreditation decisions, 5 curated journals, 1 user. M12 discontinued data page displayed that no snapshot had been imported.

## Current M14 work
- Branch `codex/m14-authorship-checker`, based on M13 production commit above; PR #9: https://github.com/arisbudianto/Publikasi/pull/9.
- Adds `/periksa/authorship`: four ICMJE criteria, 14 CRediT roles, temporary order suggestion, contribution statement, 30-person limit, no AI, no input persistence, no database migration.
- Rules version `2026-10-v1`; source details in `docs/m14/README.md`.
- Local verification: 103 tests / 752 assertions; M14 tests 8 / 47 assertions; PHP lint, JavaScript syntax, Pint, route cache, and Blade cache passed.
- GitHub Actions is the required full-suite gate before merge. M14 has not yet been deployed to production.

## Constraints and next action
- Preserve production `.env`, existing catalogue data, and secrets. Do not run production PHPUnit or `RefreshDatabase`.
- After Actions passes, merge PR #9. Then make a scoped backup, deploy the exact merge commit without migrations, rebuild only relevant caches, and smoke test `/periksa/authorship` plus `/authorship.js`.
- M14 is application-only; no database migration or production data mutation is required.
