# M05 production deployment evidence

2026-10-12 Asia/Jakarta. Application commit cc57140d65c4932426d14cb03154850aab6feef2.
PASS: preflight clean source at3a055d6, ten changed old files matched. Before migration users1/journals5/private tables absent; four prior migrations ran.
PASS: backup /home/speakver/publikasi_backups/pre-m05-20261012.tar.gz ten replaced source files, archive contents verified. New migration creates only two private tables; no existing-row updates/backfill. Code rollback leaves new tables/data intact; no destructive rollback authorized.
PASS: targeted additive migration2026_10_12_000002 ran; autoload/route/view caches rebuilt and site up. Thirteen sampled application files exactly matched source. Users1/journals5 retained; shortlists0/histories0. No default/test production accounts created.
PASS: HTTPS /,/akun/login,/akun/daftar,/cocokkan,/cocokkan-ai200; /akun without login302. Browser registration/login form app identity verified.
PASS local39 tests/261 assertions: registration role isolation, no existing-account overwrite, login/logout/rate limits, profile/password/current-password/old-session invalidation, owner-only private reads/deletes (including other admin), idempotent shortlist and archive lifecycle, explicit keyword/semantic history opt-in without abstract/context storage, regressions M01-M04.
NOT VERIFIED: authenticated live dashboard/save/history/delete flow, because owner declined secure login request. No repeated credential request. SMTP email verification/reset absent and disclosed in UI. Physical mobile not tested.
M04 follow-up: owner configured Dinoiki key; operator only checked existence. Flag enabled with owner authorization. Synthetic HTTPS POST before M05 deploy returned200, semantic result YES, DOI evidence YES, provider error NO. After M05 deploy key-present/AI-enabled bothYES. Key never read/printed/committed. No manuscript from owner used. Detailed source samples not a quality benchmark.
