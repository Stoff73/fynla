# Task 8 review — Web renderer: FynCaptureForm.vue in the chat panel

Base: 414350f70 · Head: eb1ce1e59

### Spec Compliance
- ✅ Behaviour, classes, error rendering, locked rendering, submit payload, and panel wiring (open/lock semantics, values lookup, `sendMessage({ form })`) all match the brief as amended by Ruling 1.
- ⚠️ Cannot verify from diff alone: whether Task 7's store/`sendMessage` action and Task 1's schema shape actually match the props/emit contract this component assumes (cross-task interface, not in this diff). Whether an `/m` SFC consuming `captureFormMixin` exists is also out of scope for this diff — Task 8's brief title scopes it to the web renderer only, so no defect here, but Rule 19 parity needs confirming at the whole-branch gate.

### Strengths
- **Ruling 1 followed exactly**: `resources/mobile/utils/captureFormState.js:286-373` holds every prop/emit/data/computed/method verbatim from the brief; `resources/js/components/Fyn/FynCaptureForm.vue:110-124`'s `<script>` is a 4-line import + `mixins: [captureFormMixin]` — genuinely zero logic in the SFC.
- **Import path precedent verified**: confirmed `resources/js/components/Fyn/FynQuickReplies.vue:58` uses the identical `../../../mobile/utils/<file>.js` relative-import pattern from `resources/js/` into `resources/mobile/utils/` — Task 8's `captureFormState.js` import at `FynCaptureForm.vue:119` matches it exactly, as the ruling required.
- **Named risk (a) — `v-else-if` chain, verified correct.** Read `resources/js/components/Shared/AiChatPanel.vue:180-219` pre-diff: the chain is `FynQuickReplies (v-if)` → `capture_complete (v-else-if)` → plain-message `(v-else)`. Inserting `FynCaptureForm` with a literal `v-if` (as the brief's snippet shows) would start a second, independent Vue conditional group — for a `quick_replies` message the new group's conditions are all false, so the trailing `v-else` fires too, double-rendering the fallback bubble underneath the quick replies. Using `v-else-if` (`AiChatPanel.vue:147`) keeps it inside the original chain and avoids that bug. This is a correct, necessary, and transparently disclosed fix, not a design change — endorsed.
- **Named risk (b) — mixin `data()` initialising once at mount, verified as working today, but fragile.** See Issues below.

### Issues

#### Critical (Must Fix)
None.

#### Important (Should Fix)
- **No reactivity to `values`/`locked` changing after mount; the "works" case is coincidental, and untested.** `captureFormState.js:295-309` (`data()`) reads `this.values` only once, at component creation. When a live submit happens, `AiChatPanel.vue:245-249` (`isCaptureFormOpen`) flips `locked` to `true` on the *same* component instance (the outer `v-for` key at `AiChatPanel.vue:178` is `msg.id ?? idx` keyed on the capture_form message itself, which doesn't change), so Vue does not remount and `data()` never re-runs against the new `values` prop. It currently renders correctly only because `submit()` (`captureFormState.js:359-371`) derives its payload from the same local `this.answers` that already drives the display — i.e. the locked view shows what was locally typed, not what the prop now says was persisted. If the persisted/server-confirmed `values` ever diverges from the client's local answers (rounding, a backend-side default, a spouse-share correction, a second device), the locked view would silently show the stale client value instead of the canonical one — exactly the "correct at every layer, wrong on screen" class of bug this codebase's `data-integrity-traps` guidance targets. There is no `watch` on `values`/`locked` to re-sync, and no test exercises a live prop-driven flip on an existing instance — every "locked" test in `tests/frontend/components/Fyn/FynCaptureForm.test.js:97-102` mounts fresh with `locked: true` from the start, never `setProps` on an already-mounted, already-answered instance.
  - **Fix:** add a `watch` on `values` (or `locked`) in `captureFormMixin` that re-seeds `answers`/`open`/`none` when it flips to a non-null value; add a test that mounts open, submits, then `setProps({ locked: true, values: {...} })` with a value differing from what was typed, and assert the locked view shows the prop's value, not the stale local one.

#### Minor (Nice to Have)
- Self-review overstates schema-agnosticism: `setChoice()` (`captureFormState.js:350-358`) hardcodes the field name `'ownership_percentage'`; this is plan-mandated (present verbatim in the brief), not an implementer defect, but the report's claim is inaccurate.
- Missing tests for reviewer-named edge cases (code traces out correctly by inspection but is unverified): toggling a kind closed then open again (answers persist — `toggle()` never clears `answers`); unticking "No mortgage" after ticking it (`setNone(..., false)` deletes the field and re-enables the input); switching `ownership_type` joint → individual → joint (`setChoice` deletes then re-defaults `ownership_percentage`).
- Dangling `for`/`id` mismatch on the `choice`-type field's top-level `<label :for="inputId(...)">` (`FynCaptureForm.vue:40`) — no element in the radio-group branch carries that id. Present verbatim in the brief, not introduced here; cosmetic a11y nit.

### Assessment
**Task quality:** Approved
**Reasoning:** Faithfully implements the brief and Ruling 1 — mixin holds all logic, SFC holds none, both named risks were checked and the `v-else-if` fix is correct and properly disclosed. The one Important finding (no re-sync on `values`/`locked` prop changes post-mount) is a real but currently-latent fragility, not a demonstrated bug in the tested contract, and is reasonable to fix in a fast follow-up rather than block this task.
