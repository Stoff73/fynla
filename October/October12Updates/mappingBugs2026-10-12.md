# Mapping bugs — 2026-10-12

Raised by the 2026-10-12 refresh of the application maps (commit `f00076906` on `dev`). Numbering continues from `September/September14Updates/mappingBugs2026-09-14.md`, which ends at MB-58 and carries the recheck of MB-01 to MB-58 dated 2026-10-12. Every entry is evidence from this run. Nothing here has been fixed; fixing is a separate task. Append, never renumber.

Status vocabulary: Broken, Dead, Duplicate, Dead end, Does not make sense (see `.claude/skills/app-map/SKILL.md` step 4).

## Decisions register

| Id | Status | Area | Decision needed | Decided |
|---|---|---|---|---|
| MB-59 | Dead | two spouse mailables never sent | none (delete with their templates) | — |
| MB-60 | Dead + Does not make sense | `/m` `tokens.js` unreachable; `/m` `utils/lifeEvents.js` used only by web | none | — |

### MB-59 — `SpouseAccountCreated` and `SpouseAccountLinked` are never sent
Map: docs/app-map/17-emails-notifications.md § 2.3
Status: Dead
Evidence: `git grep "new SpouseAccountCreated\|new SpouseAccountLinked"` over `app` and `routes` finds nothing at `28194e804` or at `f00076906`. `app/Services/Onboarding/SpouseLinkingService.php:631-634` says `SpouseAccountLinked` is deliberately not sent, and `SpousePermissionRequest` is sent instead (`:645`). `app/Mail/SpouseInvitation.php:15-21` describes `SpouseAccountCreated` as its predecessor, which took a temporary password, a behaviour CSJ removed on 2026-08-23. Templates `resources/views/emails/spouse-account-created.blade.php` and `spouse-account-linked.blade.php` exist. The 2026-09-14 map said both were sent; that was wrong when written.
What is wrong: two mailables and two templates are kept, and the map once described them as live.
Suspected impact: none for users; maintenance, plus the risk that someone wires the account-created mail (which carried a temporary password) back in.
Decision needed: no. Delete both mailables and their templates. `tests/Feature/Api/SpouseLinkConsentTest.php:5,209-211` asserts `SpouseAccountCreated` is not sent, as a guard against the temporary-password mail coming back. A deletion should keep that guard, for example by asserting that no mail other than `SpouseInvitation` goes out.

### MB-60 — One `/m` file has no importer, and another lives in `/m` but only web uses it
Map: docs/app-map/00-overview.md § 2.7
Status: Dead (`tokens.js`); Does not make sense (`utils/lifeEvents.js`)
Evidence: a reachability sweep of `resources/mobile` from `main.js` (imports by resolved path plus component tags, comments stripped) reaches 64 of 66 non-test files at `f00076906`, and 58 of 58 at `28194e804`. `resources/mobile/tokens.js` (a JavaScript copy of five palette colours for confetti and particle code) has no importer. The last one was removed in `997653dd7` (2026-09-17, "the level climbs in the hero circle on /m too"). `resources/mobile/utils/lifeEvents.js` has no importer inside `/m`. It is imported by web components only: `resources/js/components/Goals/LifeEventCard.vue:77` and `LifeEventDetailInline.vue:261` import `OCCURRED_LABEL` from it. `CLAUDE.md` says the `/m` bundle's only shared file is `store/modules/auth.js`.
What is wrong: a dead palette mirror that `fynlaDesignGuide.md` changes are told to keep in step ("Values MUST stay byte-identical", `tokens.js:1-5`), and a web dependency stored inside the isolated mobile bundle, which `CLAUDE.md` does not mention.
Suspected impact: none for users. The palette mirror invites edits that do nothing. A change to `lifeEvents.js` made for `/m` silently changes web, against the "a fix in `resources/js/` does not reach it" model in `CLAUDE.md`.
Decision needed: no. Delete `tokens.js`; move `OCCURRED_LABEL` to a web or shared constants file (or record the shared file in `CLAUDE.md`).
