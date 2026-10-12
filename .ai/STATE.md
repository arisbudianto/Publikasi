# Publikasi current state

PROJECT: arisbudianto/Publikasi; branch main; M08/M09 base81278a3. Runtime target /home/speakver/publikasi.speakverse.id/public; source /home/speakver/publikasi_m00_source.

M07 deployed and verified public/guest protection. M08/M09 code complete; deployment pending. M08 private manual submission/timeline/reviewer matrix/CSV, ownership checks (admin has no override), version409. M09 private admin health and CLI health, disabled-by-default optional manual payment ledger, audit events/version409. No automatic journal submission, billing/gateway, SMTP or background tasks.

VERIFIED: local full suite61 tests/450 assertions, plus final M08 pagination/search targeted5/54. PHP8.3, isolated SQLite; no real charge. Migration000005/000006 additive only. Runtime production smoke pending. Known limitation: production authenticated writes, MariaDB concurrency, backup restore, SMTP/scheduler/provider integration NOT VERIFIED.

LOCKED: Laravel13 mysql driver MariaDB approved; owner-configured .env and Dinoiki key must remain unchanged. Payment channel/provider unspecified, therefore no activation. No production tests/RefreshDatabase or default credentials. Code rollback retains additive tables/data. See docs/m08 and docs/m09/RUNBOOK.md. Do not run provision-cpanel.php. Next: source clean/drift check and scoped backup before deploy; then runtime proof and evidence update.
