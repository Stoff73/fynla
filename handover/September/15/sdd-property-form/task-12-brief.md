### Task 12: PR to dev

- [ ] **Step 1: Open the PR** against `dev` from `feat/savetax-property-capture-form` with: the spec link; one line per task; the suite counts from Task 11 Step 1; the live evidence from Task 11 Steps 3-5; the deploy notes (PHP + `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` + both bundles; no migration; run `fyn:procedural:validate`); the adjacent debt noted (duplicated `/m` message-loop template; native renderer for the form is a follow-up).
- [ ] **Step 2: Put csjones back on dev** after CSJ's go: `git checkout dev && git pull origin dev` on the server, re-cache config.
- [ ] **Step 3:** CSJ decides the merge and the release (`/release`). Never recommend deploying.

---

## Self-review against the spec

- Schema, kinds, fields, asterisks, ownership options, share default → Task 1, 8, 10.
- `turn_type: form` on the corpus-owned state → Task 3.
- `capture_form` event, persisted schema, history re-render → Tasks 4, 7, 9.
- Client capability header, native fallback → Tasks 4, 6, 7, 9, 11 Step 5.
- Submission through the one endpoint, request shape validation → Tasks 2, 6.
- Writes through `executeTool` with confirmed facts, no null optionals, advance like typed → Task 5.
- Field errors, tier cap, refresh, stale form, typed fallback → Tasks 5, 7, 8, 9, 10.
- Locked historical forms → Tasks 8, 9, 10.
- Testing on Pest, Vitest and a watched browser walk on web and `/m` → every task's tests plus Task 11.
- Out of scope held: no extractor/gate changes, no native renderer, no other step.

Type consistency: the event names `capture_form`, `capture_form_errors`, `form_received`; the row role `capture_form`; `metadata.capture_form` (schema) on the assistant row and `metadata.form` (answers) on the user row; the header `X-Fynla-Forms: 1`; `sendMessage({ form })` on web and `submitCaptureForm(form)` → `send(null, form)` on `/m`; `CaptureForms::{names,schema,rules,toolInputs,summarise,kindLabel}` — used with those exact names throughout.
