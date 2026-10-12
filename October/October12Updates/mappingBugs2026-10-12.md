# Mapping bugs — 2026-10-12

Raised by the 2026-10-12 refresh of the application maps (commit `f00076906` on `dev`). Numbering continues from `September/September14Updates/mappingBugs2026-09-14.md`, which ends at MB-58 and carries the recheck of MB-01 to MB-58 dated 2026-10-12. Every entry is evidence from this run. Nothing here has been fixed; fixing is a separate task. Append, never renumber.

Status vocabulary: Broken, Dead, Duplicate, Dead end, Does not make sense (see `.claude/skills/app-map/SKILL.md` step 4).

## Decisions register

| Id | Status | Area | Decision needed | Decided |
|---|---|---|---|---|
| MB-59 | Dead | two spouse mailables never sent | none (delete with their templates) | — |

### MB-59 — `SpouseAccountCreated` and `SpouseAccountLinked` are never sent
Map: docs/app-map/17-emails-notifications.md § 2.3
Status: Dead
Evidence: `git grep "new SpouseAccountCreated\|new SpouseAccountLinked"` over `app` and `routes` finds nothing at `28194e804` or at `f00076906`. `app/Services/Onboarding/SpouseLinkingService.php:631-634` says `SpouseAccountLinked` is deliberately not sent, and `SpousePermissionRequest` is sent instead (`:645`). `app/Mail/SpouseInvitation.php:15-21` describes `SpouseAccountCreated` as its predecessor, which took a temporary password, a behaviour CSJ removed on 2026-08-23. Templates `resources/views/emails/spouse-account-created.blade.php` and `spouse-account-linked.blade.php` exist. The 2026-09-14 map said both were sent; that was wrong when written.
What is wrong: two mailables and two templates are kept, and the map once described them as live.
Suspected impact: none for users; maintenance, plus the risk that someone wires the account-created mail (which carried a temporary password) back in.
Decision needed: no. Delete both mailables and their templates. `tests/Feature/Api/SpouseLinkConsentTest.php:5,209-211` asserts `SpouseAccountCreated` is not sent, as a guard against the temporary-password mail coming back. A deletion should keep that guard, for example by asserting that no mail other than `SpouseInvitation` goes out.
