# Publikasi current state

PROJECT: arisbudianto/Publikasi, main. Application release df62fad8a76114f8a392b7221843f3ec9ec54812 (M08/M09), deployed2026-10-12. Runtime /home/speakver/publikasi.speakverse.id/public; source /home/speakver/publikasi_m00_source.

VERIFIED: M08 private manual submission/timeline/reviewer matrix/CSV, ownership (no admin override), version409 in local tests. M09 admin/CLI health and default-OFF optional manual support ledger, audit events/version409. Full local61 tests/450 assertions; final M08 targeted5/54; route/view cache PASS. Production migrations000005/000006 DONE; users1/journals5 preserved; new rows0. Scoped backup archive contents inspected;30 installed files match source. Runtime health all PASS, queue/failed0, AI enabled/key present, payments false. Anonymous new private endpoints401; public /layanan200 identity verified. See docs/m09/DEPLOYMENT_EVIDENCE.md.

NOT VERIFIED: production authenticated writes/export/admin web; parallel MariaDB writes; mobile physical device; full DB backup/restore, SMTP, cron/worker/provider/gateway. No test account or financial transaction created. No auto journal submission, billing/paywall, upload or new background jobs.

LOCKED: Laravel13, mysql driver MariaDB approved. Preserve owner .env and Dinoiki key. Payment channel/provider unspecified so remain OFF. No production PHPUnit/RefreshDatabase/default credentials. Code rollback retains additive tables/data; backup pre-m08-m09-20261012.tar.gz only scoped code, not DB backup. Do not run provision-cpanel.php. Operational activation items documented in docs/m09/RUNBOOK.md; no permission to activate unspecified payment channel.
