# Fix wave 2: three live-walk defects — report

Branch: `feat/savetax-property-capture-form`
Worktree: `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint`

## Item 1 (web, Ruling 19) — the live form has no prompt text above it

**Cause.** `resources/js/store/modules/aiChat.js` had four independent SSE
switches (`sendMessage`, `streamNextQueued`, `postAction`,
`startOnboardingConversation`), and each duplicated the `case 'capture_form':`
body: flush `state.streamingText` into an assistant row if present, then
`commit('ADD_MESSAGE', captureFormMessage(event))`. `captureFormMessage()`
built the form row with `content: event.prompt_text || ''` — the prompt text
was baked into the *form* row's content, and `AiChatPanel.vue` never renders
a `capture_form` row's own `content`, only `<FynCaptureForm>`. So the prompt
text was silently dropped on every live stream path (fresh and resumed).
History rendered correctly only because `loadConversation`'s normalisation
already splits a persisted form turn into a separate assistant text row plus
a `capture_form` row with empty content — a different code path that already
had the right shape.

**Change.** Added one helper, `pushCaptureFormTurn(commit, state, event)`,
next to `captureFormMessage()`:
1. flushes `state.streamingText` into an assistant row if present (the
   duplicated block),
2. pushes an assistant row `{ id: 'cf_text_' + Date.now(), role: 'assistant',
   content: event.prompt_text, created_at }` when `event.prompt_text` is
   non-empty,
3. pushes the form row via `captureFormMessage(event)`, now always
   `content: ''`.

All four switches now call `pushCaptureFormTurn(commit, state, event); break;`
instead of duplicating the block. `captureFormMessage()` no longer takes
`event.prompt_text` for its own content.

**Files.**
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint/resources/js/store/modules/aiChat.js`
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint/tests/frontend/store/aiChatCaptureForm.test.js`

**RED** (aiChat.js reverted to HEAD, test updated):
```
❯ tests/frontend/store/aiChatCaptureForm.test.js (5 tests | 1 failed)
  × renders a capture_form event as a prompt-text row followed by a form row…
    AssertionError: expected 'user' to be 'assistant'
```

**GREEN** (fix restored):
```
npx vitest run tests/frontend/store resources/js/components/__tests__/Shared
 Test Files  8 passed (8)
      Tests  44 passed (44)
```

Commit: `39ca3e5ff fix(onboarding): the capture-form prompt renders on the live chat, not just in history`

## Item 2 (/m cosmetic) — the kind heading and the field hint render inside bordered boxes

**Cause.** `resources/mobile/views/Dashboard.vue` and `MobileChrome.vue` both
wrap every chat row (including a `capture_form` row's `<FynCaptureForm>`) in
`<div class="md-fyn__msg md-fyn__msg--fyn">`. `dashboard.css` has:
```css
.md-fyn__msg p { margin: 0; font-size: 0.9375rem; line-height: 1.5; padding: 0.75rem 1rem; border-radius: var(--radius-lg); }
.md-fyn__msg--fyn p { background: var(--white); color: var(--horizon-600); border: 1px solid var(--horizon-100); }
```
These target every `<p>` inside a Fyn message row generically, with higher
specificity than the form's own local rules (`.md-fyn__form-label`,
`.md-fyn__form-hint`), so they win the cascade regardless of source order.
`FynCaptureForm.vue` renders the kind heading as
`<p class="md-fyn__form-label">{{ kind.label }}</p>` and the hint as
`<p ... class="md-fyn__form-hint">` — both `<p>` tags, so both picked up the
bubble's background/border/padding, looking like input boxes. The field
`<label>` elements (a different tag) and the radio inputs were unaffected,
matching what was seen live.

**Change.** Chose the CSS-exclusion route over swapping tags to `<div>`/
`<span>` — smaller and clearer, since it touches zero markup behaviour, no
input `name`/`id`, and the copied spec asserts on button/label/input text
only.
- `FynCaptureForm.vue`: renamed the kind-heading `<p>`'s class from the
  shared `md-fyn__form-label` to its own `md-fyn__form-kind-title`.
- `dashboard.css`: added `:not(.md-fyn__form-kind-title):not(.md-fyn__form-hint)`
  to both `.md-fyn__msg p` and `.md-fyn__msg--fyn p`; added a
  `.md-fyn__form-kind-title` rule (font-weight 600, `var(--horizon-500)`,
  0.8125rem, no border/padding/background); gave `.md-fyn__form-hint` a full
  `margin` reset (`4px 0 0`) since it no longer inherits the bubble rule's
  `margin: 0` and would otherwise pick up the browser's default `<p>` bottom
  margin.

**Files.**
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint/resources/mobile/components/FynCaptureForm.vue`
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint/resources/mobile/views/dashboard.css`

**GREEN** (copied spec unchanged, plus the full `/m` suite):
```
npx vitest run resources/mobile
 ✓ resources/mobile/components/__tests__/FynCaptureForm.spec.js (5 tests)
 Test Files  35 passed (35)
      Tests  209 passed (209)
```
No RED capture for this item — it is a CSS-only rendering fix with no unit
test asserting computed style; cause was confirmed by reading the cascade
(specificity of `.md-fyn__msg--fyn p` vs `.md-fyn__form-label`/`-hint`) and
the wrapper markup directly, and the fix was verified not to regress the
existing spec.

Commit: `9cb137b1c fix(m): the capture-form kind heading and hint no longer render as boxes`

## Item 3 (director copy) — the partial-refusal sentence ends with "?."

**Cause.** `app/Services/Onboarding/OnboardingChatDirector.php::handleFormTurn`
composed each error line as:
```php
$lines[] = CaptureForms::kindLabel($form['name'], $kind).': '.rtrim($error['message'], '.').'.';
```
`rtrim($error['message'], '.')` only strips trailing `.` characters, so a
`RecaptureGuard` refusal ending in `"...or a separate one?"` is untouched,
and the unconditional trailing `'.'.` then glues on a second full stop,
producing `"...or a separate one?."` live.

**Change.** Only append a full stop when the reason has no terminal
punctuation of its own; a period-ending reason still gets normalised to
exactly one trailing period as before (preserves the old
`"message.."` → `"message."` behaviour):
```php
$reason = rtrim($error['message']);
if (str_ends_with($reason, '?') || str_ends_with($reason, '!')) {
    $fullStop = '';
} else {
    $reason = rtrim($reason, '.');
    $fullStop = '.';
}
$lines[] = CaptureForms::kindLabel($form['name'], $kind).': '.$reason.$fullStop;
```

**Files.**
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint/app/Services/Onboarding/OnboardingChatDirector.php` (line ~3763)
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint/tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`

New test: `'does not glue a full stop onto a refusal reason that already ends in
punctuation'` — mocks `CoordinatingAgent::executeTool` to return a
RecaptureGuard-shaped refusal ending in `?` and asserts the composed content
event contains `"or a separate one?"` but never `"?."`.

**RED** (fix reverted to the original `rtrim(...).'.'.` line):
```
⨯ it does not glue a full stop onto a refusal reason that already en…
  Expecting 'I couldn't save Home: You alr… one?.' not to contain '?.'.
```

**GREEN** (fix restored):
```
./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
 Tests:    11 passed (60 assertions)
```

Commit: `bfacfaf33 fix(onboarding): a refusal reason ending in ? or ! no longer gets a glued-on full stop`

## Scope

No files touched beyond the three items. `./vendor/bin/pint` run on both
changed PHP files (director + test) — passed clean, no diff.
