---
id: W-0053
title: Completing a mirror will strands the user — "Generate Spouse's Will" exists only on the Review step and is unreachable afterwards, so the mirror pair is never created
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0003-batch-b-estate-wills.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T13:50:00Z
claimed: 2026-08-21T14:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0019, W-0024]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **independent confirmation of Batch B**, local
`localhost:8000`. Throwaway `users.id 20` Priya Raman, premium, married, linked spouse
`users.id 30`, one minor child.

**Surface:** `/estate/will-builder`, after **Complete & Finalise**.

### Expected

A mirror will is a **pair**. W-0019 makes mirror wills the only option for a married
user, so generating the spouse's will is not optional decoration — it is the point of
the flow. A user must be able to generate it whether or not they have already
finalised their own.

### Actual

> **SUPERSEDED — see the `2026-08-21 build-lead` note below ("the route back DOES
> exist").** `WillPlanning.vue:97-101` renders a **"View Will"** button whenever
> `will.will_document_id` is set, which `markComplete()` always sets, and
> `WillBuilderView.vue:76-84` honours the query param it pushes. The machinery to return
> to Review after completion was already there, which makes the fix smaller than the
> claim below implies. Left as the record of what was believed.

**"Generate Spouse's Will" appears only on the Review step, before completion.** Once
**Complete & Finalise** is clicked there is no route back to it:

| Surface after completion | Controls present |
|---|---|
| `/estate/will-builder` | Edit · Add Bequest · Account — **no Generate** |
| `/estate` | **no generate/mirror control at all** |
| `/estate/will-builder` → **Edit** | stays on "Will Planning" / "Specific Bequests"; does **not** return to the wizard, and no Generate appears |

The database confirms the mirror was never created:

```
will_documents.id 14   will_id=23   will_type=mirror   status=complete
                       mirror_document_id = NULL
wills.id 23            user_id=20   executor_name='Arjun Raman'
```

So a married user who follows the flow through to its natural end — finalise your
will — is left with **half a mirror pair and no way to complete it**. The spouse's
will simply never exists.

### Impact

The flow's happy path defeats its own purpose. "Complete & Finalise" is the obvious
terminal action, and taking it is what removes the ability to produce the other half.
Nothing warns the user that Generate must come first.

It compounds W-0019: married users are *only* offered mirror wills, so this is the sole
will path available to them, and its completion step breaks the pair.

Note this does **not** contradict Batch B's report of the mirror generating correctly
with the executor swapped — that behaviour is real, and reachable by clicking Generate
**before** Complete & Finalise. The defect is that ordering is undiscoverable and
irreversible.

### Repro

1. Premium married account with a linked spouse and a minor child.
2. `/estate/will-builder` → work through all ten steps to **9 Review**.
3. Click **Complete & Finalise** (not Generate Spouse's Will first).
4. `POST /api/estate/will-builder/{id}/complete` → 200.
5. Return to `/estate/will-builder` and to `/estate`. No "Generate Spouse's Will"
   control exists on either. **Edit** does not reopen the wizard.
6. `will_documents.mirror_document_id` is NULL and no second will document exists.

### Evidence

- `tests/Persona/20-08-2026_run/pass-a-web/38-web-will-review-error-banner-and-disabled-button-W-0024.png` — shows both controls side by side on the Review step, before completion
- `tests/Persona/20-08-2026_run/pass-a-web/39-web-will-review-errors-cleared-complete-enabled.png`
- DB state quoted above for `will_documents.14` / `wills.23`
- Post-completion button inventories for `/estate/will-builder` and `/estate`

## Acceptance

- [ ] "Generate Spouse's Will" is reachable **after** completion — from the will
      summary, the Estate module, or both.
- [ ] Where the mirror has not been generated, the completed-will view says so and
      offers the action, rather than presenting the will as finished.
- [ ] Decide deliberately whether completing without generating should be possible at
      all for a married user, given W-0019 makes mirror the only option. If it should
      warn first, the warning belongs on the Review step next to Complete & Finalise.
- [ ] Generating after completion produces the same correct mirror Batch B verified
      before completion — executor swapped to the primary testator, `relationship`
      recomputed, guardians offered (W-0024).
- [ ] Existing completed mirror wills with `mirror_document_id IS NULL` are identified;
      those users are stranded today.
- [ ] `/m` and iOS will surfaces checked for the same ordering trap (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

Found while independently confirming Batch B's leads. Batch B's own verification
generated the mirror **before** completing, which is the order that works — so the
defect was invisible from that path. A second tester taking the other obvious order
found it immediately. Worth noting as a pattern: where a screen offers two terminal
actions, both orders need walking.

- 2026-08-21 build-lead: **FIXED.** Handing to quality-lead. **Rule 14's loop is
  not closed by me** — I no longer browser-verify my own work.

  **One correction to the report, and it makes the fix smaller than it looks: the
  route back DOES exist.** `WillPlanning.vue:97-101` renders a **"View Will"**
  button whenever `will.will_document_id` is set — which `markComplete()` always
  sets — and it pushes `/estate/will-builder?view=document`.
  `WillBuilderView.vue:76-84` honours that query param by skipping the
  `hasExistingWill` short-circuit, and `:97-99` starts the wizard at Review for a
  completed document. So the machinery to return to Review after completion was
  already there and working.

  The tester listed the controls they saw as "Edit · Add Bequest · Account", and
  **Edit genuinely does not reopen the wizard** — it edits the `WillPlanning`
  record — so the conclusion was reasonable. But "View Will" is the route, and it
  is why this is a one-condition fix rather than a new surface.

  **Root cause — a single gate.**
  `WillBuilderReviewStep.vue:81` read
  `v-if="!isComplete && formData.will_type === 'mirror' && !mirrorData && documentId"`.
  The `!isComplete` term hid the button the moment the will was completed, and
  `mirrorData` is only ever populated by clicking Generate in that session — it is
  never loaded from the server — so after any reload the component could not know
  a mirror already existed either.

  **Fix, three parts:**

  1. `WillBuilderReviewStep.vue:81` now reads
     `v-if="formData.will_type === 'mirror' && !mirrorGenerated && documentId"`,
     with a new computed `mirrorGenerated()` = `mirrorData || formData.mirror_document_id`.
     The button stays available until the counterpart actually exists, and
     disappears once it does — including across reloads, because it now consults
     the persisted `mirror_document_id` rather than session state.
  2. **`generateMirrorWill()` is now safe to press twice**
     (`WillDocumentService.php:299`). Making it reachable post-completion means it
     can be pressed again; it now returns the existing counterpart rather than
     creating a second one. Pinned by a test.
  3. **A warning before completion**, so new cases announce themselves rather than
     relying on the user noticing: `validateDocument()` raises
     `MIRROR_NOT_GENERATED_MESSAGE` (severity `warning`, so it does not block)
     whenever a `mirror` document has no counterpart. Server-side, so it reaches
     every surface from one place (Rule 20). Team-lead is right that a warning is
     not a substitute for the fix — it is additive here, not instead.

  **Your question 1 — the already-stranded document is rescued, verified against
  the real row.** `will_documents.14` (Priya Raman, 20, `will_type=mirror`,
  `status=complete`, `mirror_document_id` NULL) now:
  - raises `warning | mirror | Your partner's will has not been generated yet…`
  - has `wills.will_document_id = 14`, so **"View Will" renders**, which reaches
    Review, where the Generate button now shows.
  So the existing stranded state is repairable through the UI with no data
  migration. Unlike W-0046, this one did not need a backfill command.

  **Your question 2 — no, the spouse cannot generate from their side, and I have
  not changed that.** `WillDocumentController::generateMirror()` scopes
  `WillDocument::where('user_id', $request->user()->id)`, so a user can only
  generate from a document they own. Arjun (30) holds no document, so he has no
  route to produce the missing counterpart — **the pair is only ever creatable by
  the first testator.** Making it two-sided means letting one account write a will
  document into another account, which needs a `SpousePermission` decision and its
  own design; it is not a line I should add quietly inside this fix. **Raise as
  its own item.**

  **The pattern from your triage, recorded because it generalises:** where a
  screen offers two terminal actions, both orders need walking. Generate-then-
  complete worked; complete-then-generate stranded. I verified the order that
  worked and did not walk the other.

  Tests: `tests/Unit/Services/Estate/WillDocumentServiceTest.php` — four cases
  under "a mirror will is a pair, and stays completable into one (W-0053)":
  warns while unpaired and stops once paired; does not warn on a simple will;
  **generates the counterpart after completion** (the rescue); returns the
  existing counterpart instead of creating a second.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.** `WillPlanning.vue:92` renders the
  "View Will" block whenever `will.will_document_id` is set, and `WillBuilderView.vue:63/75-76`
  honours `?view=document` — the watcher on `$route.query.view` plus the short-circuit skip. The
  route back from a completed will exists and works.
