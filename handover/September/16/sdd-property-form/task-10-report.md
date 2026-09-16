# Task 10 report — `/m` renderer: `FynCaptureForm.vue` in both chat templates

## What I implemented

1. **`resources/mobile/components/FynCaptureForm.vue`** (new) — the `/m` renderer for a
   `capture_form` chat turn. Per Ruling 1, the `<script>` block is exactly:
   ```js
   import { captureFormMixin } from '../utils/captureFormState.js';
   export default { name: 'FynCaptureForm', mixins: [captureFormMixin] };
   ```
   plus a four-line header comment. All state, validation and payload logic lives in the
   one shared mixin (`resources/mobile/utils/captureFormState.js`, already the home for
   both the web and `/m` renderers per Task 9's contract). The template is the web
   template (Task 8, `resources/js/components/Fyn/FynCaptureForm.vue`) with the class
   swaps the brief specified: `md-fyn__form`, `md-fyn__form-kind` (+ `--open`),
   `md-fyn__form-section`, `md-fyn__form-field`, `md-fyn__form-label`,
   `md-fyn__form-money`, `md-fyn__form-choices`, `md-fyn__form-hint`,
   `md-fyn__form-error`, `md-fyn__form-actions`, plus the existing global `.m-field` on
   inputs and `.m-btn` on Save. Every `name`, `id`, `type`, `value` and `disabled`
   attribute is identical to the web template.

2. **CSS** — appended the brief's `.md-fyn__form*` block to
   `resources/mobile/views/dashboard.css` directly after `.md-fyn__bubble:disabled`
   (verbatim from the brief). All palette values confirmed to exist as CSS custom
   properties in `resources/mobile/style.css`: `--raspberry-500`, `--raspberry-600`,
   `--raspberry-50`, `--horizon-200`, `--neutral-500`, `--font-primary`, `--radius-md`.

3. **Two template insertions**, one line each (plus import + registration):
   - `resources/mobile/views/Dashboard.vue` — `FynCaptureForm` imported
     (`../components/FynCaptureForm.vue`), registered in `components: { GamificationCelebration, FynCaptureForm }`,
     and the `<FynCaptureForm ... />` tag added directly after the `.md-fyn__bubbles`
     block's closing `</div>`, inside the `v-for="(m, i) in messages"` loop.
   - `resources/mobile/components/MobileChrome.vue` — `FynCaptureForm` imported
     (`./FynCaptureForm.vue`); this file had no `components:` block, so I added one
     (`components: { FynCaptureForm }`); same tag added in the same position in its
     (separately duplicated, pre-existing) message loop.
   - Did not touch the duplication between the two files' message loops — noted as
     pre-existing debt per the brief.

4. **Test**: `resources/mobile/components/__tests__/FynCaptureForm.spec.js` (new) — an
   exact copy of `tests/frontend/components/Fyn/FynCaptureForm.test.js` (Task 8) with
   only the import path changed to `'../FynCaptureForm.vue'`. Same five cases, unchanged
   assertions.

## Ruling 10 applied

Skipped the brief's Step 4 mobile bundle build (`npm run build:mobile`). Ran only the
Vitest command; the deploy script builds both bundles later.

## TDD evidence

**RED** — `npx vitest run resources/mobile/components/__tests__/FynCaptureForm.spec.js`
(before the component existed):
```
FAIL  resources/mobile/components/__tests__/FynCaptureForm.spec.js [ resources/mobile/components/__tests__/FynCaptureForm.spec.js ]
Error: Failed to resolve import "../FynCaptureForm.vue" from
"resources/mobile/components/__tests__/FynCaptureForm.spec.js". Does the file exist?
...
 Test Files  1 failed (1)
      Tests  no tests
```

**GREEN** — same command, after writing the component:
```
✓ resources/mobile/components/__tests__/FynCaptureForm.spec.js (5 tests) 106ms

 Test Files  1 passed (1)
      Tests  5 passed (5)
```

## Full test run

`npx vitest run resources/mobile`:
```
 Test Files  35 passed (35)
      Tests  209 passed (209)
```
All 35 `/m` spec files pass, output pristine, no Vue warnings, no console noise.

## Lint

`npx eslint resources/mobile/components/FynCaptureForm.vue resources/mobile/views/Dashboard.vue resources/mobile/components/MobileChrome.vue resources/mobile/components/__tests__/FynCaptureForm.spec.js`
— no output, clean.

## Files changed

- `resources/mobile/components/FynCaptureForm.vue` (new)
- `resources/mobile/components/__tests__/FynCaptureForm.spec.js` (new)
- `resources/mobile/views/Dashboard.vue` (import, `components`, one template tag)
- `resources/mobile/components/MobileChrome.vue` (import, new `components` block, one template tag)
- `resources/mobile/views/dashboard.css` (`.md-fyn__form*` rules)

Commit: `2e2fe17e2 feat(m): FynCaptureForm renders a capture_form turn in the /m chat and posts its answer`

## Self-review

- **Completeness**: component, CSS, both template insertions (each with import +
  registration) present; the copied spec passes unchanged (5/5, same assertions as
  Task 8's web test). ✅
- **Quality**: no state/validation/payload logic in the SFC — `<script>` is the
  two-line mixin wiring per Ruling 1, verified against the file content. ✅
- **Discipline**: no icons, no emoji, no Unicode-as-icons in the new component or CSS
  (grepped, clean); no hex colours, only `var(--token)` (grepped, clean); no
  YAGNI additions — implemented exactly the classes and structure the brief specified,
  nothing extra. ✅
- **Testing**: full `/m` suite green (209/209), output pristine, no warnings. ✅

## Issues or concerns

None. One thing worth flagging for the controller's own tracking rather than a defect:
`MobileChrome.vue` had no pre-existing `components:` option (it presumably relied
entirely on globally-registered or auto-imported components before now), so this task
adds the first one. This is a natural, minimal addition, not a refactor — flagging only
because it's the first `components:` key in that file.
