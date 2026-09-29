# Tech Debt Report — Session 2026-09-29 (session 3)

**Files analysed:** 103 changed on dev in e2378727d..5629b6b6a (this session's own changes plus the review fixes applied to the 21 Icecube-acc PRs)
**Issues found:** 14 open (the session 2 critical, literal SSP fallbacks, is fixed in #997)
**Severity breakdown:** 2 critical, 8 warnings, 4 suggestions

## Critical Issues

1. **`app/Services/Onboarding/SpouseHoldingTransfer.php` (other income from #990) and the spouse's own onboarding** — Data integrity. When a spouse links, the inviter's figure is split into pay (an estimate the spouse's own job replaces, #963) and other income (`users.annual_other_income`, stored as usual per CSJ). If the spouse's own onboarding then records the same pension or rent as a record, it is counted twice (`IncomeDefinitionsService` counts pension income and `other` separately). The same risk exists for every record the transfer creates: savings, the ISA, investments and the pension. The spouse's own onboarding can ask again and add a second record.
   **Fix (agreed direction, not built):** extend `WalkFormPrefill` (#978) so each walk form whose answer the transfer wrote opens that record as an edit (values + record), so Save updates it instead of adding a second one; then walk a spouse's full onboarding on web and `/m`.
2. **`public/pages/savetax-plan-v2.php:196,203,258,262` and `savetax-plan-v3.php`** — Rule 2 / Rule 23. Publicly routed mock-up pages (`/savetax/plan/v2`, `/v3`) type tax figures in: £3,000, £500, £18,750, £100,000, 60%.
   **Fix:** read them from `TaxConfigService` as `savetax-plan.php` does, or drop the v2 to v4 mock-up routes if they are no longer used.

## Warnings

1. **`app/Services/Onboarding/OnboardingStateMachine.php` (`adoptLinkedSpouseWorkStatus`, from #978)** — Pattern. A `skip_if` predicate writes `household_calculation_mode`. Any read-only caller of the skip rules (a progress preview) would write silently.
   **Fix:** make the predicate pure (`linkedSpouseEarnings() !== null`) and write the mode in the director's transition step.
2. **`app/Http/Controllers/Api/AiChatController.php::streamTurn` (from #976)** — Behaviour. With `ignore_user_abort(true)`, pressing Stop no longer stops the server: the answer is still generated, billed and stored. The server cannot tell Stop from a dropped connection.
   **Fix:** a small `POST …/conversations/{id}/stop` that sets a short-lived flag, which `streamTurn` checks between events; web's `abortStreaming` calls it.
3. **`resources/js/store/modules/aiChat.js` (`waitForTurn`) and `resources/mobile/mixins/onboardingChat.js` (`TURN_SETTLE_MS`)** — Behaviour. A retried turn the server is still finishing is reloaded after a fixed 3 seconds; a longer turn reloads without its reply yet.
   **Fix:** poll the transcript until an assistant row follows the user row, with a cap.
4. **`app/Http/Middleware/IdempotencyKeyMiddleware.php`** — Behaviour (now load-bearing for web and `/m` retries). The key hash includes the request body, and the body includes `current_route`, so a retry after the user has navigated is a new request and runs again.
   **Fix:** leave `current_route` out of the body hash for the messages route.
5. **`resources/js/components/Protection/CoverageGapsSection.vue` and `resources/mobile/views/modules/Protection.vue`** — Cross-bundle duplication. `fieldLabel`, `displayInput`, `displayAssumption` and `displayDate` are written twice.
   **Fix:** have `ProtectionGapPresentationService` return display strings (label and formatted value) for inputs and assumptions.
6. **`app/Services/Tax/TaxStrategyMath.php::nonEarnerFundableGross`** — Service locator: `app(CrossModuleAssetAggregator::class)`.
   **Fix:** inject it through the constructor.
7. **`app/Services/UserProfile/UserProfileService::updateIncomeOccupation`** — Data integrity (pre-existing, only partly fixed). The income page still writes `annual_employment_income` directly; only the estimate case now goes through `EmploymentIncomeService`. With two or more job rows, the page total and the rows can differ, and the next `syncTotals()` puts the row sum back.
   **Fix:** send the page's employment and self-employment totals through `EmploymentIncomeService` for every case.
8. **Carried from session 2, still open:**
   - four places assemble protection needs and coverage (`ProtectionCoverPosition`, `ProtectionGapPresentationService`, `ProtectionAgent` ×2);
   - `consolidate()` re-queries `getEnabled()`;
   - the `evaluateCoverPosition()` stub;
   - cover and benefit wording written twice, once per bundle;
   - `app()` used as a locator in `handleCaptureEmployerBenefits`.

## Suggestions

1. **`resources/mobile/utils/fynStream.js`, `AiChatController::STREAM_TERMINAL_EVENTS`, iOS `FynEvent.isTerminal`** — The terminal-event list is written three times (clients have to detect a cut-off, so this is tolerable).
2. **`resources/mobile/utils/fynStream.js::isDroppedConnection`** — Treats every `TypeError` as a network drop, so a client bug gets offered "Try again".
3. **`resources/mobile/views/Dashboard.vue:~1003`, `MobileChrome.vue:~459`** — Still parse `/api/auth/user` themselves, alongside `store.userFromResponse`.
4. **`public/pages/index.php:452-465` (stats bar)** — "91% UK adults don't get financial advice" and "1000's of financial plans created for people like you" carry no source (Rule 23).

---
*Generated by tech-debt-session skill*
