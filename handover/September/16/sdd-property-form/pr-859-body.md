## Save Tax property capture form

Replaces free-text capture at the Save Tax onboarding property step (`campaign_property`) with a structured form inside the Fyn chat, on web and `/m`. Native iOS is untouched: the form is sent only to clients that declare `X-Fynla-Forms: 1`, and every other client keeps the typed prompt, byte-identical to before this branch.

Spec: `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md`
Plan: `docs/superpowers/plans/2026-09-15-savetax-property-capture-form.md`

### What changed, by task

1. `CaptureForms` is the one schema home: kinds, fields, labels, the asterisk rule, `toolInputs()` for `create_property`, `summarise()` for the transcript line, per-field validation rules.
2. `SendAiChatMessageRequest` accepts an optional `form`; `message` is required only without it; rules are filtered to the kinds actually submitted.
3. The corpus makes `campaign_property` a `form` turn. `prompt_text` stays the typed instruction (native); the form wording lives in a new `form_prompt_text` data key.
4. The director emits a `capture_form` event only when the client declared forms, and persists the schema on the assistant row so history and resumed conversations re-render it.
5. The director saves a form answer through `CoordinatingAgent::executeTool('create_property')`, one write per kind, reports partial failures as `capture_form_errors` on the refused kind and keeps the step parked; the fact extractor is skipped on form turns.
6. The controller reads the header once and carries the flag and the form through send, queue, stream, start and action (the resume path).
7. Web store and API: the header on all four SSE fetches, one `sendMessage` path for text or `{ form }`, `capture_form` / `capture_form_errors` / `form_received` in all four routers, history normalisation, a refused form re-opens.
8. Web renderer `FynCaptureForm.vue` in the chat panel; all state, validation and payload logic lives once in `resources/mobile/utils/captureFormState.js` as a mixin shared with `/m`.
9. `/m` transport and mixin: header on `apiStream`, the three events, `submitCaptureForm` through the one `send` path, transcript mapping with answers taken from the persisted user row.
10. `/m` renderer in both chat templates (dashboard chat and the docked bar).

### Tests

- Pest (Onboarding, Unit Onboarding, AI, Unit AI, Property, Tiers, Architecture): 2438 passed, 5 skipped, 0 failed at `2e2fe17e2`; the fix waves re-ran Onboarding + Unit Onboarding + AI: 1607 passed, 4 skipped, 0 failed.
- Vitest: 1355 passed, 146 files, 0 failed.

### Live evidence on csjones (branch deployed, CSJ's Chrome)

- Native-style request (no header) at the property step streams the typed prompt and no `capture_form` event.
- Web fresh path (user 397 `formwalk-0915@example.com`): form renders after the income step; asterisks; Joint reveals the share at 50; No mortgage disables the amount; Save gated until valid; both kinds saved in one submission; transcript line; locked form with values; verify page shows £750,000 / share £375,000 / mortgage liability £162,500 / equity £212,500 and the £450,000 buy to let; DOB prompt follows. DB: two property rows, one mortgage row of £325,000, no fact parked as income.
- Web resume path: reload, Continue, the form renders (action endpoint).
- Web refusal path: Home refused by the duplicate check, buy to let saved, the form re-opened with the error under Home and values intact.
- Web history: prompt-text row plus locked form with values.
- `/m` fresh path (user 398 `formwalk-m-0915@example.com`): form in the docked chat with the prompt text; Home joint 50; locked after Save; `/m` property page shows £375,000 jointly owned, mortgage £162,500; DOB prompt follows.
- Defects found in the walk and fixed on the branch, then re-verified live after redeploy: the live `capture_form` event now renders its prompt text above the form on web (Ruling 19); the `/m` kind heading, hint and error line render as plain text; a refusal reason ending in `?` no longer gets a full stop glued on; the flush and prompt rows carry distinct ids.
- `/m` refusal path: duplicate Home refused, error line under Home, form re-opened with values, no "?.".
- The Free-plan cap was not reachable live (the parked test user resolves to Premium via entitlements on csjones); it is covered by the tier test in `PropertyCaptureFormTurnTest`.

### Deploy notes

- PHP + `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (the corpus must ship with the code) + both bundles (`public/build/`, `public/m-build/`).
- No migration. Run `php artisan fyn:procedural:validate` after deploy; the onboarding workflow must list as active.
- csjones is currently on this branch; put it back on `dev` after merge.

### Rulings taken during the build (full text in the SDD ledger)

1. Form logic lives once in a shared mixin under `resources/mobile/utils/`, imported by the web SFC by relative path (the `FynQuickReplies` precedent).
2. The `/m` message loop stays duplicated in `Dashboard.vue` and `MobileChrome.vue` (pre-existing debt; one tag added to each).
3. Request validation filters the rules to the submitted kinds.
4. Tasks 3 and 4 executed as one dispatch.
5. An empty set of recognised kinds parks the step with a form-level error instead of advancing.
6. The action endpoint (Continue on resume) also carries the forms flag.
7. A refused form never trips the empty-response banner.
8. The composed summary line stands as the user row on a refused form.
9. `/m` transcript answers come from the next user row's `metadata.form.answers`.
10. No raw `npm run build`; the deploy script builds both bundles.
11. No watcher on `values` in the mixin: the persisted answers are the client's own payload.
12. The `loadTranscript` rename was reverted.
13. A refused form re-opens with its errors on both surfaces.
14. After a reload a parked step re-emits a fresh form on Continue; the refused form in history stays locked.
15. A form posted at a non-form state gets a friendly message, not a 422 (spec deviation, accepted).
16. Second homes are not offered by the form (per spec); raised for CSJ below.
17. `form_prompt_text` corpus key; native keeps the pre-branch typed prompt.
18. The fact extractor is skipped on form turns.
19. The live `capture_form` event renders its prompt text as its own assistant row.

### For CSJ to decide

- The form offers Home and Buy to let only, so the walk no longer asks about a second home anywhere.
- After a partial refusal the saved kind stays editable in the re-opened form; a resubmit of that kind hits the duplicate check.
- A form posted at a non-form state returns a friendly message rather than the spec's 422.

### Adjacent debt (not fixed here)

- The `/m` message-loop template is duplicated in `Dashboard.vue` and `MobileChrome.vue`.
- Native renderer for the form is a follow-up once CSJ has seen the form.
- 19 minors triaged by the final review as shippable; listed in the SDD ledger.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_01FsNdiEPbMNCfBaSbZLb5JZ
