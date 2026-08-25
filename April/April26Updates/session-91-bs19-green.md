---
tags:
  - april-2026
  - session-summary
  - bug-fix
  - bs-nn
date: 2026-04-26
session: 91
---

# Session 91 — BS-19 GREEN + RecordDuplicateChecker upgrade

**Date:** 2026-04-26
**Branch:** `feature/fyn-persona-split`
**Commit:**

- `bc53e7e` — fix(ai): broaden RecordDuplicateChecker to short-circuit duplicate retries (BS-19 GREEN)

Back to [[April Index]]

---

## What happened

### BS-19 — gap-fill dedup on retry GREEN

Drove BS-19 via the canonical seeded advice-mode walk (john@example.com, User #352, advice mode — `onboarding_completed=false` AND `onboarding_fyn_step=null`, so `AiChatController::sendMessage:174-176` resolves `$inOnboarding=false` → AdviceFyn).

**Run 1 (conv 86):** dispatched the canonical multi-entity message — `"I have Aviva life insurance £300k and Vitality critical illness £100k"`. Stream produced user + entity_created(life) + entity_created(ci) + assistant + capture_complete("Saved 2 records"). DB: `LifeInsurancePolicy::whereDate(today)::count = 1` (L#92 Aviva £300k level_term); `CriticalIllnessPolicy::whereDate(today)::count = 1` (C#8 Vitality £100k standalone). `/protection` page rendered the two policy cards.

**Run 2 pre-fix (conv 87):** RED. Dispatching the IDENTICAL message a second time produced a second pair of entity_created events + capture_complete. DB count went to 2 + 2 (L#93 + C#9 duplicates created at 18:00:32, ~75 seconds after the originals).

### Bug-fix-in-loop (CLAUDE.md Rule #15) — `RecordDuplicateChecker` upgrade

**Diagnosis:** `AdviceFyn::handle:127` calls `RecordDuplicateChecker::alreadyExists` BEFORE routing to `handleInlineCapture`. The previous `protectionPolicyExists` used a narrow regex requiring `"with/at/from <Provider>"` phrasing — does NOT match `"I have <Provider>"`, so it returned `false`. Inline-capture fired; the LLM (data_capture persona) emitted `create_protection_policy` x2; `handleCreateProtectionPolicy` persisted both AGAIN. The 24h DB dedup in `AssetCaptureEntityExtractor::findMissing` (`$user`-aware) protects gap-fill emissions but only runs AFTER the LLM stream — by then the LLM-direct `create_*` had already written.

**Fix** (`app/Services/AI/RecordDuplicateChecker.php`):

- Replaced the regex-based `protectionPolicyExists` with a generic `allEntitiesExist` that delegates to `AssetCaptureEntityExtractor::extractForFocus` + `::findMissing([], $user)`.
- Constructor now DI's `AssetCaptureEntityExtractor`.
- When `$missing === []` (every entity the deterministic extractor finds is already in DB <24h), return `true` → `AdviceFyn` falls through to the read-only LLM advice path → no inline-capture → no LLM-direct write.
- The Pest sibling (`tests/Feature/AI/GapFillDedupTest.php`) already covers `findMissing`'s extract+dedup logic in isolation; the fix reuses it at the routing-gate layer rather than re-implementing.
- Removed the now-unused regex helpers (`extractProvider`, `extractCurrencyAmount`) and the `LifeInsurancePolicy` model import.

**Re-verify (run 2 post-fix, conv 88):** GREEN. After deleting L#93 + C#9 to reset state, dispatching the identical message a third time produced just user + assistant — zero entity_created, zero capture_complete, zero fill_form, zero gap-fill synthesised events. Assistant content (advice persona, conversational): _"Thanks for confirming those details, John. You have £300,000 level term life insurance with Aviva and £100,000 standalone critical illness cover with Vitality – that's a solid foundation for protection..."_ DB unchanged: 1 + 1. `/protection` page still showed exactly 2 cards.

Two screenshots committed under `docs/sprint-0-verification/BS-19/`:

- `01-first-run.png` — protection page after run 1, two policy cards visible.
- `02-after-retry.png` — protection page after run 2 post-fix, still two cards (no duplicates).

### BS-22 parked — spec amendment filed

CSJ explicitly corrected during the session: AI chat consent is granted at registration via the privacy policy. There is no UI toggle for it and there should not be one. The BS-22 stub script step 6 ("Toggle off AI chat consent" via Settings/Privacy) is invalid because it assumes a flow that does not exist in production. The runtime consent gate (`AiChatController::sendMessage:152` + mid-stream re-check at `:192`) is real defence-in-depth for backend revocation paths (GDPR action / admin / account deletion / direct DB update by an operator), and the Pest sibling at `tests/Feature/AI/ConsentRuntimeCheckTest.php` already covers it via direct DB UPDATE.

Filed `feedback_ai_chat_consent_no_toggle.md` in memory (with index entry in `MEMORY.md`) so this trap doesn't recur. Filed BS-22 under the spec-amendments list in CSJTODO with the decision options for CSJ: either (a) amend BS-22 to simulate operator-revoke via tinker `UserConsent::update(['consented' => false])` and verify the frontend `consent_required` SSE handler renders the gate modal correctly (the only thing the Pest sibling can't cover), or (b) defer BS-22 like BS-23 and let the Pest sibling carry the contract alone.

### Other session work

- `php artisan db:seed --force` ran clean at session start.
- Pest baseline sweep — 486 / 1591 / 0 (pre-fix) and 486 / 1591 / 0 (post-fix). No regression. The `RecordDuplicateChecker` constructor change is auto-wired via Laravel's container.
- Cleaned a stale `AiDailyUsage` row carried over from session 90's BS-21 walk (24913 tokens). Logged as fixture-cleanup, not a code fix.

---

## Tech debt findings

0 issues across 1 changed code file (`RecordDuplicateChecker.php`). The fix is small, scope-disciplined, has a descriptive docblock explaining the rationale, and removes more code than it adds (net −47 / +29 in the file). Test stub (`BS-19-gap-fill-dedup-on-retry.php`) is just docblock narrative — no code change.

---

## Context for next session (92)

BS-19 closes Batch 3 at **9 GREEN** (BS-01, 02, 04, 06, 07, 10, 13, 19, 21).

**3 actionable scenarios remaining:** BS-15, 17, 18. (BS-05 deferred to PSP-LS/PSP-S; BS-22 + BS-23 parked pending spec amendment; BS-17 still blocked by WriteIntentClassifier extension prep.)

Recommended next pick: **BS-15** (hash-chain audit admin view) — read-only / admin-view assertion only, likely the cleanest. **BS-18** (SSE abort keep writes) is the alternative — needs a real abort sequence (kick a streaming turn, abort the network request, verify any tool calls that already fired still persisted).

The new `allEntitiesExist` pattern in `RecordDuplicateChecker` is now the model for extending dedup to other entity types when `WriteIntentClassifier` extension is unblocked — extract via `AssetCaptureEntityExtractor::extractForFocus(<focus>)`, dedup via `findMissing(<focus>, $extracted, [], $user)`, no per-type regex required.
