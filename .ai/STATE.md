# Publikasi current state

PROJECT `arisbudianto/Publikasi`; default branch `main`; Laravel 13; production app `https://publikasi.speakverse.id`; cPanel source `/home/speakver/publikasi_m00_source`; app path `/home/speakver/publikasi.speakverse.id`.

## Verified production baseline
- M14 deployed at commit `c109a944b2f9dc31464d541f7912816f6c1e941f`; current GitHub `main` matches this commit.
- M14 backup `/home/speakver/publikasi_backups/pre-m14-20261013-075614.tar.gz`, SHA-256 `82e0bd7bcc8aed9851f7ff96f7b5860b9e612016b70d3b478e01a4aefb950a3c`.
- `/periksa/authorship`, `authorship.js`, and both GET/POST routes were verified. POST returned HTTP 200 and the expected ICMJE result marker.
- Existing production catalog counts remain 999 source records, 2,697 SINTA accreditation decisions, 5 curated journals, and 1 account. M14 made no database change.

## Current objective: M15 pilot
- Build `/posisi-riset` for authenticated accounts: OpenAlex metadata search, optional editable Indonesian-to-English translation, semantic ranking, evidence-bound analysis, RIS/BibTeX and print-to-PDF export, opt-in account history.
- Dinoiki pilot ceiling US$5/month and maximum 20 analyses/month; 2 per account/day and 4 global/day. Existing `DINOIKI_API_KEY` remains in production `.env`; never print or request it in chat.
- M15 adds one aggregate usage table only. Saved history excludes the abstract. Search input is sent externally only after consent; provider retention is not audited.
- PR #10 is open from `codex/m15-research-positioning`, based on M14 commit above; head `4cf29139f66a69454ee5460260e7ae13d20d8d3b`.

## Verification
- Source snapshot tree matches GitHub M14 tree `fc2fab76ff0fb8e604759654ee9bd518b48a2e8d`.
- M15 focused tests: 6 tests / 42 assertions PASS. Full local suite: 109 tests / 794 assertions PASS.
- PHP syntax, JS syntax, Pint, route cache, and Blade cache checks PASS.
- Production M15 migration, external provider behavior, 20-case human relevance review, and cost review are not yet verified.

## Constraints and next action
- Preserve production `.env`, catalog data, and secrets. Never run production PHPUnit or `RefreshDatabase`.
- Before M15 deployment, create and verify a database backup. Apply the additive usage-table migration once, then deploy exact merged code, rebuild route/view caches, and smoke test authenticated M15 steps.
- M15 is not yet merged or deployed. GitHub Actions run 59 is in progress; after it passes, merge PR #10 and continue with the bounded cPanel backup/deployment batch.
