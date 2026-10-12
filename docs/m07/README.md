# M07 Submission readiness checklist

Public /jurnal/{id}/checklist shows general ten-item guide with official journal link. User must follow actual journal instructions; no journal-specific requirements invented. Checklist covers scope, originality, authors, contextual ethics requirements, manuscript format, references, figures/permissions, blind-review instructions, cover declarations and optional supplements. Status pending/done; na permitted only for supplements. Completion is self-assessment, not editor verification/acceptance guarantee. No automatic submission or document upload.

Authenticated save stores user/journal unique row with JSON answers, note max1000 and version. Owner sees own answers only, even admin cannot read other user notes. Guest cannot persist. Profile lists paginated saved checklists. Archived journal owner may read historical checklist but cannot update. Optimistic version check409 prevents stale-tab overwrite; transactional journal lock and unique constraint prevent duplicates. No public sharing, secrets or manuscript uploads. Private responses no-store.

Migration000004 additive new table with owner/journal FKs. No existing data overwritten. Code rollback leaves private checklist table/data intact; drop requires explicit destructive approval. Checklist notes are stored until future deletion/account deletion, backups may retain copies. No checklist-delete flow in M07.

TESTED PHP8.3:50 tests/335 assertions PASS: guest read/save boundary, progress persistence, version conflicts, owner isolation including other admin, archived lifecycle, unknown-key/status validation, optional-na restrictions, regression M01-M06.
NOT VERIFIED predeploy: live authenticated save, real MariaDB concurrency race, physical mobile. User previously reported admin login/menu access; no secret requested in chat.
