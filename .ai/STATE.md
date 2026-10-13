# Publikasi current state

PROJECT `arisbudianto/Publikasi`; default branch `main`; Laravel 13; production `https://publikasi.speakverse.id`; source `/home/speakver/publikasi_m00_source`; runtime `/home/speakver/publikasi.speakverse.id`.

## Verified current state
- M15 PR #10 merged as `2aed3f5d4483ea32bb113f04e4bd5696fae12b41`; runtime files compared with source PASS; config, route, and Blade caches rebuilt; application live.
- M15 additive migration Ran [11]. `m15_ai_usage` verified: 12 columns, InnoDB, unique period index.
- Guest `/posisi-riset` HTTP 302; `/m15.css` and `/m15-export.js` HTTP 200. M15 readiness true; limits US$5/month, 20 analyses/month, 2/account/day, 4/global/day.
- Code backup `/home/speakver/publikasi_backups/pre-m15-code-20261013-082831.tar.gz`; SHA-256 `d4ce13871fb5af1ca9bdc7180a8cc89ba6eeb88ac8cecb42453ffc615f9bc295`.
- DB backup `/home/speakver/publikasi_backups/pre-m15-db-20261013-083149.sql.gz`; gzip and dump checks PASS (26 tables, migrations present); SHA-256 `e85897fa824b17a67c0fef770d505675acdbfc2c5a50b68d0a49efe9214b5e90`. Restore not tested in this deployment.
- Previous catalog counts: 999 sources, 2697 accreditation decisions, 5 curated journals, 1 account; not recounted after M15.

## Verification and current issue
- M15 local suite PASS: 109 tests / 794 assertions; focused tests 6 / 42; PHP/JS syntax, Pint, route/view caches PASS. GitHub Actions run 60 PHP 8.3 and 8.4 PASS.
- Owner screenshot shows consent checkbox expanding because shared input CSS applies width 100%. Branch `codex/m15-consent-layout` explicitly sets checkbox width auto and constrains consent text.
- CSS fix not yet deployed. Local automated browser layout check could not run because browser executable is unavailable.
- Authenticated external OpenAlex/Dinoiki analysis, exports, optional history, human relevance review, and cost review NOT VERIFIED in production.

## Constraints and next action
- Preserve production .env, catalog data, and credentials. Never run production PHPUnit, RefreshDatabase, or destructive migration rollback.
- Use subshells for operator batches so set -e failures do not close the terminal. Match migration status by basename, not full path.
- After CSS PR checks pass, merge and deploy only CSS plus state documentation. Verify consent layout in browser, then run one synthetic English analysis using existing account and explicit consent. Do not repeat completed migration or baseline tests without relevant changes.
