### Task 10: `/m` renderer — `FynCaptureForm.vue` in both chat templates

**Files:**
- Create: `resources/mobile/components/FynCaptureForm.vue`
- Modify: `resources/mobile/views/Dashboard.vue` (:321-335) and `resources/mobile/components/MobileChrome.vue` (:141-155) — one tag each inside the message loop
- Modify: `resources/mobile/views/dashboard.css` — `.md-fyn__form*` classes after `.md-fyn__bubble` (:1879)
- Test: `resources/mobile/components/__tests__/FynCaptureForm.spec.js` (create)

**Interfaces:**
- Consumes: the schema; row `m.form = { schema, errors, answers, locked }`; mixin `submitCaptureForm(form)` (Task 9); `formatCurrency` from `resources/mobile/utils/currency.js` for the locked display
- Produces: props `schema`, `errors`, `disabled`, `locked`, `values`; emit `submit(form)` — the same contract as the web component

The behaviour and the emitted payload are identical to Task 8 (same kinds, fields, asterisks, none box, share reveal, Save gating, errors, locked). The differences are only presentational: no Tailwind on `/m`, so classes are `md-fyn__form`, `md-fyn__form-kind` (+ `--open`), `md-fyn__form-field`, `md-fyn__form-label`, `md-fyn__form-hint`, `md-fyn__form-error`, and the existing `.m-field` on inputs and `.m-btn` on Save. Palette via `var(--raspberry-500)`, `var(--horizon-200)`, `var(--neutral-500)`, `var(--raspberry-600)`; radius via `var(--radius-md)`.

- [ ] **Step 1: Write the failing test**

Copy `tests/frontend/components/Fyn/FynCaptureForm.test.js` from Task 8 to `resources/mobile/components/__tests__/FynCaptureForm.spec.js`, changing only the import to `../FynCaptureForm.vue`. The same five cases must pass — the two renderers share one contract.

- [ ] **Step 2: Run to verify it fails**

Run: `npx vitest run resources/mobile/components/__tests__/FynCaptureForm.spec.js`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the component, the CSS and the two template lines**

Component: the Task 8 template with the class swaps above (the `<script>` is the same code, copied — the bundles are isolated by architecture and share no modules except `store/modules/auth.js`). Keep the `name`/`id` attributes identical to Task 8 so the shared test passes.

CSS, appended after `.md-fyn__bubble:disabled` in `dashboard.css`:

```css
.md-fyn__form { padding: 8px 0; }
.md-fyn__form-kinds { display: flex; flex-wrap: wrap; gap: 8px; }
.md-fyn__form-kind {
  background: var(--white);
  border: 2px solid var(--raspberry-500);
  color: var(--raspberry-500);
  padding: 0.5rem 0.875rem;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  font-family: var(--font-primary);
  cursor: pointer;
}
.md-fyn__form-kind--open { background: var(--raspberry-50); }
.md-fyn__form-kind:disabled { opacity: 0.5; cursor: not-allowed; }
.md-fyn__form-section { margin-top: 12px; border: 1px solid var(--horizon-200); border-radius: var(--radius-md); padding: 12px; background: var(--white); }
.md-fyn__form-field { margin-top: 10px; }
.md-fyn__form-label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--neutral-500); margin-bottom: 4px; }
.md-fyn__form-money { display: flex; align-items: center; gap: 8px; }
.md-fyn__form-choices { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.875rem; }
.md-fyn__form-choices label { display: flex; align-items: center; gap: 4px; }
.md-fyn__form-hint { font-size: 0.75rem; color: var(--neutral-500); margin-top: 4px; }
.md-fyn__form-error { font-size: 0.75rem; color: var(--raspberry-600); margin-top: 4px; }
.md-fyn__form-actions { margin-top: 12px; }
```

Template line, added in **both** `Dashboard.vue` and `MobileChrome.vue` directly after the `.md-fyn__bubbles` block inside the message loop:

```html
<FynCaptureForm
  v-if="m.form && m.form.schema"
  :schema="m.form.schema"
  :errors="m.form.errors"
  :disabled="sending"
  :locked="m.form.locked"
  :values="m.form.answers"
  @submit="submitCaptureForm"
/>
```

Import and register `FynCaptureForm` in both files' `components`. (Two one-line template insertions; the behaviour lives in the one component. The duplicated message-loop template itself is pre-existing debt — note it in the PR, do not refactor it here.)

- [ ] **Step 4: Run the tests and build the bundle**

Run: `npx vitest run resources/mobile && npm run build:mobile 2>&1 | tail -3`
Expected: tests pass; the mobile bundle builds without warnings about the new component.

- [ ] **Step 5: Commit**

```bash
git add resources/mobile/components/FynCaptureForm.vue resources/mobile/views/Dashboard.vue resources/mobile/components/MobileChrome.vue resources/mobile/views/dashboard.css resources/mobile/components/__tests__/FynCaptureForm.spec.js
git commit -m "feat(m): FynCaptureForm renders a capture_form turn in the /m chat and posts its answer"
```

---

