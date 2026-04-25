# Tech Debt Report — Session 75 (2026-04-25)

**Scope:** session 75 commits (`ff8e4ed` → `786d841`) on `feature/fyn-persona-split` — Sprint 0.9 / 0.10 + Trust direct-write test contract + Will faker flake fix.
**Files analysed:** 13 (6 production, 7 tests)
**Issues found:** 2 (0 critical, 0 warnings, 2 suggestions)

The session shipped Sprint 0.9 (consent runtime check) and 0.10 (user-content sanitisation + structural separation), updated the TrustObserver test to pin the post-S0.5.l direct-write contract (the agent persists the Trust + observer fires the CLT Gift), and pinned the long-known `WillBuilderApiTest` faker flake. Verified end-to-end live (Trust→CLT chain produces correct Gift row; AdvicePromptBuilder emits `<user_provided>James</user_provided>` for the user-profile layer). Full Pest suite: 2799/2799 passing, 10958 assertions, 0 failures. Production code introduced 0 TODOs, 0 debug dumps, 0 hardcoded tax values, 0 banned colour tokens, 0 acronyms in user-facing copy.

---

## Critical Issues

None.

---

## Warnings

None.

---

## Suggestions

### S1. Mid-stream consent re-check fires one DB query per SSE event

- **File:** `app/Http/Controllers/Api/AiChatController.php:165-178`
- **Category:** 4 (complexity / scalability)
- **What's there:** Every yield from the LLM generator triggers a fresh indexed `EXISTS` query against `user_consents` to detect mid-stream consent withdrawal. For a typical 30–100-yield response this is 30–100 queries (sub-millisecond each, ~30–100ms cumulative per stream).
- **Why it works today:** The query is the cheapest possible — single composite index hit, returns boolean. Total stream overhead is bounded because chat responses cap around 100 events. Behaviour is correct and the spec's mid-stream contract is honoured.
- **Suggested fix (defer):** Throttle to every Nth event (e.g. N=10) OR cache consent state for the stream duration with explicit invalidation on the consent-update endpoint. Either keeps the contract while flattening DB pressure under load. Natural fit for S0.11 reliability bundle, not a blocker.

### S2. Duplicated "grant ai_chat consent" helper across test files

- **Files:**
  - `tests/Feature/AI/ConsentRuntimeCheckTest.php:22` — defines `grantAiChatConsent(User)`
  - `tests/Feature/Onboarding/StartOnboardingEndpointTest.php:18` — defines `grantAiChatConsentForOnboardingEndpointTest(User)` (renamed to dodge the function-redeclaration collision Pest's global-function autoload triggers)
  - `tests/Feature/Onboarding/StateMachineWalkthroughTest.php:42-44` — calls `app(ConsentService::class)->recordConsent(...)` inline in `beforeEach`
  - `tests/Feature/Fyn/HandoffInvisibilityTest.php:18` — calls `app(ConsentService::class)->recordConsent(...)` inline
- **Category:** 1 (duplicate code)
- **What's there:** Four places do the same thing (record ai_chat consent for a factory user) with three different shapes. The `*ForOnboardingEndpointTest` rename is a workaround for Pest's global-function autoloading.
- **Suggested fix (defer):** Extract to a small `tests/Helpers/AiChatTestHelpers.php` (or a Pest-compatible trait used via `uses()`) with a single canonical helper. Low value, low urgency — only matters once 5+ test files need consent setup. Worth bundling with any future test-helper consolidation pass.

---

## Notes & Confirmations

- `app/Services/AI/Prompts/UserContentSanitiser.php` — clean. Two static methods, single-purpose, documented allow-list rationale + non-ASCII trade-off in class-level docblock. No state, no dependencies.
- All wrapping sites in `AdvicePromptBuilder.php` use `(string)` cast before `wrap()` so null-ish DB columns don't trigger `TypeError`.
- Enum-typed columns (`trust_type`, `business_type`, `ownership_type`, `property_type`, `relationship`, `marital_status` etc.) are deliberately NOT wrapped — they come from fixed sets and cannot carry prompt-injection payloads. Documented in S0.10 commit message.
- `<user_provided>` boundary survives an attacker-supplied forgery test (`tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php:90-103`) — the inner content is provably free of `<` and `>` because `clean()` strips them before wrapping.
- `hasConsent()` re-check uses an indexed query (`user_id, consent_type, version, consented`) directly — does not rely on the closed-over `$user` model's relations, so a withdrawn consent is observed even mid-stream.
- All test files declare `strict_types=1`, use `it()` / `RefreshDatabase` / `TaxConfigurationSeeder`, and follow the project Pest conventions.
- The `WillBuilderApiTest` fix is a one-line `'middle_name' => null` factory override — pinpoint fix for the faker flake CSJTODO had carried since session 72.
- Trust → CLT chain verified live via `php artisan tinker`: `executeTool('create_trust', initial_value: 250000)` produced both the Trust row AND the matching `Gift` row (`gift_type='clt'`, `gift_value=250000`, `recipient='Live Test Trust'`, `gift_date='2026-04-20'`) in the same transaction. Observer behaviour preserved post-S0.5.l direct-write conversion.

---

## Carried from session 74 (still open)

- **W1 carried** — `CoordinatingAgent.php` is now 3,718 lines (no change this session — S0.9/0.10 didn't touch the agent file). Sprint 4 backlog candidate.
- **S1 carried** — `handleUpdateRecord` field aliasing chain. Defer.
- **S2 carried** — `handleListInvoices` queries `Invoice` directly rather than via a `User->invoices()` relation. Defer.

---

*Generated by tech-debt-session skill — session 75, 2026-04-25*
