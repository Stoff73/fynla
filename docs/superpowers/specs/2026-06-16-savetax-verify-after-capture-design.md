# SaveTax Campaign — Verify-After-Capture + Income/Expenditure Screens

**Date:** 2026-06-16
**Surface:** `/m` only (the SaveTax campaign section-walk is `/m`-native; there is no web equivalent to mirror).
**Status:** Approved in brainstorming (2026-06-16). Ready for implementation plan.

## 1. Problem & goal

During the **SaveTax campaign onboarding** (the `/m` Fyn-dock walk that captures financial data section-by-section and writes it to the user's account), Fyn captures each section's data and immediately advances to the next section. The user never sees *what* was added and can't confirm it's right before moving on.

**Goal:** after Fyn writes each campaign section's data, run a short **verify loop** — confirm there's nothing else to add, navigate the user to the screen showing the added data, let them confirm it's correct, and edit it if not — before advancing. Two campaign captures (income, expenditure) have no screen to navigate to today, so we also build two net-new `/m` screens.

## 2. The flow (per campaign section, after the section's capture completes)

1. **Gate 1 — "Anything else to add here?"** `[Yes / No]`
   - **Yes** → re-enter the section's capture, then return to Gate 1 (loop until No).
   - **No** → step 2.
2. **Navigate.** Fyn says *"Navigating you to the information I just added,"* emits a `navigation` SSE event → the chat **minimises** and the app routes to the section's screen. The docked **nudge bar stays visible**. The **Gate 2** prompt + bubbles ride the *same* SSE turn, so they are waiting in the (minimised) chat when the user reopens it.
3. **Gate 2 — "Is this information correct?"** `[Yes / No]` (user reopens the chat to answer)
   - **Yes** → advance to the next section (`nextCampaignSection()`).
   - **No** → step 4.
4. **Edit.** Fyn asks *"What needs changing?"* → the user describes it → Fyn calls `update_record` / `update_profile` (already whitelisted in the campaign) → re-navigates to the screen → back to Gate 2. Loop until Yes.

**Charitable giving** is the exception: no screen exists and none is wanted, so Fyn does Gate 1 + an **inline** Gate 2 ("I've recorded your charitable giving of £X — correct?") in chat, with the same Yes/No + edit branch, but **no navigation**.

## 3. Capture → verify-destination mapping

| Campaign section | Capture writes | Verify destination |
|---|---|---|
| income | `users.annual_employment_income` + self-employment / rental / dividend / other; spouse income on the spouse `User` row | **new `/m` Income screen** |
| savings | savings accounts (incl. ISA) | `/savings` (existing) |
| investments | investment accounts + holdings | `/investment` (existing) |
| pensions | DC pension + contributions/history | `/retirement` (existing) |
| giving | `users` charitable-giving fields | **inline confirm** (no navigate) |
| spouse | spouse work status + spouse income/household | **new `/m` Income screen** (shows spouse income) |
| expenditure | `users.monthly_expenditure` / `annual_expenditure` | **new `/m` Expenditure screen** |

## 4. Architecture — generic verify sub-flow (chosen approach: B)

Rejected: (A) hard-code explicit verify states per section (~20 states of bloat); (C) drive the loop from the frontend (moves flow off the deterministic state machine, harder to keep one source of truth). **Chosen: B** — one reusable verify sub-flow in `OnboardingStateMachine`, parameterised by the current section + its route.

### 4.1 State machine (`app/Services/Onboarding/OnboardingStateMachine.php`)
- After a campaign section's terminal capture state, instead of going straight to `nextCampaignSection()`, route into the verify sub-flow for that section. The sub-flow remembers which section it is verifying (carried in onboarding context, not 7× duplicated states).
- Sub-flow states (generic, section-parameterised):
  - `STATE_VERIFY_MORE` — `turn_type='bubbles'`: *"Anything else to add to your {section}?"* `[Yes/No]`. Yes → the section's capture entry state; No → `STATE_VERIFY_NAVIGATE`.
  - `STATE_VERIFY_NAVIGATE` — emits the "navigating…" content + `navigation` (the section's route from the §3 table) **+** the Gate 2 `quick_replies` in the same turn. For `giving` it skips the `navigation` and emits only the inline Gate 2. Next state: awaiting the Gate 2 answer.
  - Gate 2 answer handling — Yes → `nextCampaignSection(currentSection)`; No → `STATE_VERIFY_EDIT`.
  - `STATE_VERIFY_EDIT` — `turn_type='delegated'` with the existing `update_record` / `update_profile` tools: *"What needs changing?"* → applies the edit → back to `STATE_VERIFY_NAVIGATE` (re-show + re-ask Gate 2).
- A small `verifyRouteForSection(string $section): ?string` map (income→`/income`, savings→`/savings`, investments→`/investment`, pensions→`/retirement`, spouse→`/income`, expenditure→`/expenditure`, giving→`null`).

### 4.2 SSE emission (`app/Services/Onboarding/OnboardingChatDirector.php`)
- Reuse `emitTurnForState` (`bubbles`) for Gate 1 / Gate 2, and the `navigation` emission used by `emitTerminalNavigationTurn` for the mid-campaign navigate (generalised so it isn't terminal-only). The "navigating…" turn must emit the `navigation` event **and** the Gate 2 `quick_replies` together so the bubble is queued before the chat closes.
- Edit turn runs through the existing `handleInlineCapture` → `CoordinatingAgent::update_record` / `update_profile`.

### 4.3 Frontend (`resources/mobile/views/Dashboard.vue`)
- Mid-campaign `navigation` already routes via `handleOnboardingNavigation` (`closeFyn()` + `$router.push`). Verify the cursor **queues the Gate 2 `quick_replies` bubble onto the message before** `handleOnboardingNavigation` closes Fyn, so the bubble is present when the user reopens (the docked nudge bar's `openFyn`). Add a tweak only if the close drops the just-streamed bubble.
- New routes `/income` + `/expenditure` registered in `resources/mobile/router.js`.

## 5. Two net-new `/m` screens

Both: `MobileChrome` wrapper, mobile design tokens, **no scores** (Rule #12 — show £ values), **no decorative icons** (Rule #15), acronyms spelled out (Rule #9). Data from `GET /api/user/profile` (the complete profile, incl. income/expenditure fields; spouse via the linked `User`).

- **Income** (`resources/mobile/views/Income.vue`, route `/income`, name `m-income`): the user's income broken down — employment, self-employment, rental, dividend, other — and, when a spouse is linked, the spouse's income alongside. Currency-formatted cards.
- **Expenditure** (`resources/mobile/views/Expenditure.vue`, route `/expenditure`, name `m-expenditure`): monthly and annual expenditure.

### 5.1 `/m` drawer nav
Add a **"Cash Management"** group to the `/m` drawer (in both `MobileChrome.vue` and `Dashboard.vue` `navSections`, mirroring the desktop side nav) with **Income** and **Expenditure** links (nav-surface icons, consistent with the approved mobile drawer design). So the screens are reachable any time, not only during onboarding.

## 6. Edit mechanism (already exists — no new tools)

`OnboardingPromptBuilder::toolsForFocus('savetax')` already whitelists `update_record` and `update_profile`:
- `update_profile` (section `income_occupation` / `expenditure`) → `users.*` income + expenditure fields.
- `update_record` (entity `savings_account` / `investment_account` / `dc_pension` / …) → account/pension rows, allowlist-guarded.

Gate 2's "No → what needs changing" delegated turn drives these via the existing `handleInlineCapture` path. No new write tools, no change to the read-only advice contract.

## 7. Testing

- **Unit** (`tests/Unit/Services/Onboarding/…`): verify sub-flow transitions per section — Gate 1 Yes loops the capture, Gate 1 No → navigate, Gate 2 Yes → next section, Gate 2 No → edit → re-navigate. `verifyRouteForSection` mapping. Giving uses inline (no navigation event).
- **Feature**: the two new `/m` screens' data endpoint (`GET /api/user/profile`) returns income/expenditure incl. spouse.
- **Browser E2E (Rule #14, `/m` on csjones)**: walk the SaveTax campaign end-to-end; for each section confirm Gate 1 → navigate (correct screen) → Gate 2 → edit path; confirm the new Income/Expenditure screens render the captured values and appear in the drawer nav; confirm giving is inline. Per `reference_savetax_campaign_e2e_test_pattern`.

## 8. Success criteria

1. After each campaign section's capture, Gate 1 ("anything else?") appears; Yes re-captures, No proceeds.
2. On No, the chat minimises, the app navigates to the section's screen (per §3), and the nudge bar is visible.
3. On reopening the chat, Gate 2 ("is this correct?") is waiting.
4. Gate 2 No → Fyn asks what to change, edits the record via `update_*`, re-shows the screen; Yes → advances.
5. New `/m` Income (user + spouse) and Expenditure screens render the captured data and are reachable from the drawer's Cash Management group.
6. Charitable giving verifies inline (no navigation).
7. Verified on `/m` (csjones) end-to-end.

## 9. Out of scope

- Web/desktop (the campaign is `/m`-native).
- Other onboarding flows (only the SaveTax campaign).
- New write/update tools (the existing whitelisted `update_record` / `update_profile` cover the edit path).
- Restyling the existing `/savings` / `/investment` / `/retirement` screens.
