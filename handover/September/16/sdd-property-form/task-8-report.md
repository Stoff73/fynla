# Task 8 report — Web renderer: FynCaptureForm.vue in the chat panel

## What I implemented

Per the brief, amended by Ruling 1 (logic lives in a shared mixin, not duplicated per surface):

1. **`resources/mobile/utils/captureFormState.js`** (new) — `captureFormMixin`, a plain
   object exporting the brief's `props`, `emits`, `data()`, `computed` and `methods`
   verbatim (kind-open state, per-field answers, the `none` map for `money_or_none`,
   `isValid`, `visibleFields`/`conditionMet` for `required_when`, `setNumber`/`setNone`/
   `setChoice`, and `submit()` which emits `{ name, answers }` for only the opened kinds).
   Two-line header comment states it is the one home for this state/validation/payload
   logic, shared by web and `/m`.

2. **`resources/js/components/Fyn/FynCaptureForm.vue`** (new) — the brief's `<template>`
   and `<style scoped>` verbatim. `<script>` imports `captureFormMixin` via the relative
   path `../../../mobile/utils/captureFormState.js` (the same pattern as
   `FynQuickReplies.vue:58`) and applies it as a mixin; the SFC itself holds no logic.

3. **`resources/js/components/Shared/AiChatPanel.vue`** — wired in per the brief's anchors:
   - Import at :440 (next to `FynQuickReplies` import at :439), registration at :454.
   - Template block inserted after the `FynQuickReplies` block, before the
     `capture_complete` block (around :190).
   - `latestCaptureFormIndex` computed added after `latestQuickRepliesIndex` (~:614).
   - `isCaptureFormOpen`, `captureFormValues`, `handleCaptureFormSubmit` methods added
     after `handleQuickReplySelect`, before `handleNavigation` (~:1270).

4. **`tests/frontend/components/Fyn/FynCaptureForm.test.js`** (new) — the brief's 5 test
   cases verbatim.

## One deliberate deviation from the brief's literal snippet

The brief's `AiChatPanel.vue` snippet shows `<FynCaptureForm v-if="msg.role === 'capture_form'" ...>`.
I used **`v-else-if`** instead. Reason: `FynQuickReplies` (`v-if`) → `capture_complete`
(`v-else-if`) → a final catch-all `v-else` (regular message row) already form one Vue
conditional chain in this template (confirmed at :219 in the original file). Inserting a
fresh `v-if` between two links of that chain does not attach to it — Vue treats it as
starting a *second*, independent chain, which the following `capture_complete` and the
final `v-else` would then attach to instead. Net effect: for a `quick_replies` message,
the second chain's conditions are all false, so its `v-else` would fire too, rendering the
plain-message fallback underneath the quick-reply bubbles — a real double-render bug, not
a design choice. Using `v-else-if` keeps `capture_form` as another branch of the original
chain, matching how `FynQuickReplies` and `capture_complete` are already wired, and avoids
the bug. This is a syntax fix to preserve the brief's intended behaviour, not a behaviour
or design change — flagging it per the self-review discipline.

## TDD evidence

**RED** — `npx vitest run tests/frontend/components/Fyn/FynCaptureForm.test.js`
(before creating the component):
```
FAIL  tests/frontend/components/Fyn/FynCaptureForm.test.js
Error: Failed to resolve import "@/components/Fyn/FynCaptureForm.vue" ... Does the file exist?
Test Files  1 failed (1)
     Tests  no tests
```

**GREEN** — same command, after creating the mixin + SFC:
```
✓ tests/frontend/components/Fyn/FynCaptureForm.test.js (5 tests) 105ms
Test Files  1 passed (1)
     Tests  5 passed (5)
```

## Step 4 — full covering suite

`npx vitest run tests/frontend/components/Fyn tests/frontend/store`:
```
✓ tests/frontend/store/investmentHoldings.test.js (4 tests)
✓ tests/frontend/components/Fyn/FynCaptureForm.test.js (5 tests)
✓ tests/frontend/store/savingsIsaAllowance.test.js (6 tests)
✓ tests/frontend/store/aiChatEvents.test.js (4 tests)
✓ tests/frontend/store/aiChatCaptureForm.test.js (4 tests)
✓ tests/frontend/store/aiChatAuthExpiry.test.js (5 tests)
✓ tests/frontend/store/savingsEmergencyFundGetters.test.js (10 tests)

Test Files  7 passed (7)
     Tests  38 passed (38)
```
The two `stderr` lines that print are deliberate negative-path logging from pre-existing
tests (`aiChatAuthExpiry.test.js`, `savingsIsaAllowance.test.js`), unrelated to this task —
no Vue warnings, no console noise from the new component or its tests.

## ESLint

`npx eslint resources/js/components/Fyn/FynCaptureForm.vue resources/js/components/Shared/AiChatPanel.vue resources/mobile/utils/captureFormState.js tests/frontend/components/Fyn/FynCaptureForm.test.js`
→ no output, no errors.

## Files changed

- `resources/mobile/utils/captureFormState.js` (new)
- `resources/js/components/Fyn/FynCaptureForm.vue` (new)
- `resources/js/components/Shared/AiChatPanel.vue` (modified)
- `tests/frontend/components/Fyn/FynCaptureForm.test.js` (new)

## Self-review findings

- **Completeness**: every behaviour bullet in the brief is covered by the 5 tests (closed
  state + disabled Save; asterisks and `required_when` hiding; percent field reveal/default
  50 + Save enabling; `money_or_none` → `null` on submit, only opened kinds emitted;
  kind-level and field-level errors rendered in `text-raspberry-600`; `locked` renders
  disabled inputs with `values` and no Save button).
- **Mixin holds all logic, SFC holds none**: confirmed — the SFC's `<script>` is a 4-line
  import + `mixins: [captureFormMixin]`, nothing else.
- **Discipline**: no icons (asterisk is text), no hex in `<style>`, only palette/global
  classes (`form-input`, `form-hint`, `label`, `btn-primary btn-sm`, `raspberry-*`,
  `horizon-*`, `neutral-*`, `light-gray`) — all pre-existing in `app.css`. Canonical enums
  only (`main_residence`, `buy_to_let`, `individual`, `joint`, `tenants_in_common`) — these
  come from the test's own schema fixture, not hardcoded in the component (the component is
  schema-agnostic about kind/field names).
- **Unicode/emoji check**: scanned all four changed/new files for emoji and Unicode-arrow
  ranges — found none introduced by this task (two pre-existing `↔`/`→` characters in
  `AiChatPanel.vue` comments, unrelated to my diff, left untouched per Rule 15's
  forward-only clause).
- **v-else-if fix**: documented above; not a design/behaviour change, a chain-integrity fix.

## Issues or concerns

- None blocking. The `v-else-if` deviation above is the only departure from the brief's
  literal text, and I believe it is required to avoid a real bug — flagging for the
  controller's awareness per the task instructions.
