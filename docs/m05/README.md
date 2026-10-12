# M05 Accounts, private history and shortlist

Public registration/login at /akun/daftar and /akun/login; /akun requires signed-in session. Registration normalizes email, hashes password, requires confirmation and minimum12 characters, forces is_admin=false and does not verify email. Existing admin accounts can log into personal account; public registration never overwrites accounts/roles. Login per-email+IP five failed attempts/min plus route throttle; registration3/min. CSRF, session regeneration, no password old_input. Authenticated session password-hash check invalidates old sessions after password change. Name-only profile update; password requires current password.

No SMTP verification/reset configured: feature clearly discloses missing email verification and email password recovery. Account email cannot be edited here. No claim of verified identity. Public catalog/matching remains usable without account.

Shortlist is user_id+journal_id unique, owner-scoped, idempotent add. Only published journal may be added; archive later shows unavailable in personal list. Owner may remove item. History opt-in checkbox saves only title/mode and public candidate snapshot (id/name/source/date); never manuscript abstract/context/vectors or matched input terms. Guest requests cannot persist history. AI failures do not create history. History timestamp source UTC; UI date/time currently UTC. Lists paginated, private/no-store; admin role has no access to other users' personal lists. Owner can delete history; deletion is permanent. Data retained until owner deletion, with backups retaining historical copies if created by operator.

Migration2026_10_12_000002 creates two tables, FKs to existing users/journals; additive, no backfill/data overwrite. Rollback code only: leave private tables/data intact and restore modified application files from3a055d6. Do not migrate:rollback after personal data exists without explicit destructive-operation approval.

TESTED PHP8.3:39 tests /261 assertions PASS: signup no role escalation/overwrite, login/logout, password/current-password/session invalidation, shortlist idempotency/lifecycle, cross-user and admin ownership read/delete boundaries, keyword+semantic opt-in no abstract retention, rate limit, regressions M01-M04.
NOT VERIFIED predeploy: live authenticated account flow, SMTP. No production default/test accounts.
