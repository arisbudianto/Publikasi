# Publikasi state
M05 code deployed; migration and public production smoke PASS. App cc57140; local39 tests/261 assertions PASS. Authenticated production private workflow NOT VERIFIED (owner declined login). See docs/m05/DEPLOYMENT_EVIDENCE.md.
M04 Dinoiki key detected, enabled, live synthetic semantic+article DOI smoke200 PASS before M05 deploy; flag/key still present after M05. Do not inspect/store key. Provider/corpus quality and retention not audited.
M05 accounts, name/password profile, private shortlist, opt-in title/candidate history; no abstract/context retention. SMTP verification/reset not configured. Ownership excludes other admins. No default production accounts.
Tables personal_shortlists and match_histories added; old users1/journals5 preserved. Backup pre-m05-20261012.tar.gz verified. Code rollback retains tables/data; no destructive DB rollback authorized.
Target /home/speakver/publikasi.speakverse.id/public; source /home/speakver/publikasi_m00_source. Never run provision-cpanel.php on configured deployment.
