---
id: W-0133
title: Completing a will is a one-way door — "Complete & Finalise" never returns, so a gift edited or a bequest deleted after finalising can never be re-synced and the will document and the Estate module diverge permanently
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T19:15:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0023, W-0041, W-0046, W-0053]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **batch B regression pass**, local
`localhost:8000`, driven in Playwright as **Arjun Raman, `users.id 30`**, premium,
married to `users.id 20`, holding the mirror document `will_documents.15` generated
from Priya's `will_documents.14`.

**Surface:** `/estate/will-builder` — the Will Planning screen's bequest list and the
will-builder wizard's Review step.

Raised because batch B reported a related behaviour it did not raise:

> "**Reported, not fixed:** deleting a bequest carrying a `will_document_id` removes
> the row, but a later re-completion recreates it from the document's gifts."
> — `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` §2c

Driven from the interface, **the opposite is true and it is worse**: there is no later
re-completion. The row cannot come back.

### Expected

`WillDocumentService::syncBequests()` turns the document's gifts into `Bequest` rows
and runs on completion (`markComplete()` → `:482`). Those rows are what the Estate
module, `WillAnalysisService`, the charitable total and the `/m` bequests screen all
read. A user must therefore be able to reach the sync again after finalising — either
by re-finalising, or by the document and the rows being kept in step some other way.
At minimum, the will the app shows the user and the bequests the app tells the user it
holds must not be able to disagree with no route to reconcile them.

### Actual

**1. "Complete & Finalise" never appears again.**

`WillBuilderReviewStep.vue:156-158` — `isComplete() { return this.formData.status === 'complete'; }`
— and the button is gated `v-if="!isComplete"` (`:100`). `formData` is seeded from the
document itself (`WillBuilderView.vue:95-99`), and editing a step issues
`PUT /api/estate/will-builder/{id}`, which never touches `status`.
`WillDocumentController::update()` (`:144`) does not sync bequests; only `complete()`
(`:177`) does.

Driven end to end, not inferred. From the completed will: **View Will** → Review →
**Edit Will** → then Continue through **every** step — Intro, Personal, Executors,
Guardians, Gifts, Residuary, Funeral, Digital — back to Review. Each step saved
(`PUT … → 200`). At Review the only buttons are **Edit Will** and **Print / Save PDF**
(`64-web-arjun-review-after-full-edit-walk-no-complete-button.png`).

So **any gift added, corrected or removed after finalising never becomes a `Bequest`
row** — it renders in the will document and is invisible to the Estate module, the
charitable total and `/m`.

**2. Deleting a document-sourced bequest leaves the two records permanently at odds.**

Arjun's will was completed with two gifts, producing two rows (both
`will_document_id = 15`). Deleting one from the Estate screen succeeded —
`DELETE /api/estate/bequests/55 → 200`, "Bequest deleted successfully"
(`62-web-arjun-delete-document-sourced-bequest-success.png`) — and the document was
not touched:

```
will_documents.15.specific_gifts
  [{"type":"cash","amount":5000,"conditions":"Receive at age 25, held in trust",
    "beneficiary_name":"Meera Raman"},
   {"type":"cash","amount":10000,"beneficiary_name":"Cancer Research UK"}]

bequests
  55  Meera Raman          £5,000   will_document_id 15  deleted_at 2026-08-21 18:56:43
  56  Cancer Research UK  £10,000   will_document_id 15  deleted_at NULL
```

The user is now shown both of these, at the same time, and cannot make them agree:

| Surface | Shows |
|---|---|
| `/estate/will-builder` → View Will (the will itself) | "(a) The sum of £5,000 to Meera Raman, Receive at age 25, held in trust. (b) The sum of £10,000 to Cancer Research UK." |
| `/estate/will-builder` → Specific Bequests | Cancer Research UK £10,000 only |
| `/m` → `/m/app/estate/bequests` | "**1 BEQUEST** — Cancer Research UK £10,000" |

Evidence: `63-web-arjun-completed-will-still-lists-deleted-gift-no-recomplete.png`
and `69-m-arjun-bequests-synced-from-will-document.png`.

### Impact

**The bequest list is what the money follows.** `getCharitableBequestTotal()` reads
`Bequest` rows, not the document, so after W-0020 the Inheritance Tax **rate** follows
the rows too. Delete a charitable bequest from the Estate screen and the will still
recites the legacy, the user still believes it is left — and the tax calculation
silently stops counting it. There is no way back through the interface. The same
mechanism runs the other way: a legacy added to the will after finalising is recited in
the document, counts for nothing in the calculation, and cannot be made to count.

This also makes finalising quietly destructive of the flow's own affordances. Nothing
warns the user that **Complete & Finalise** is the last time their gifts can reach the
rest of the app, and "Edit Will" invites them to believe otherwise — it walks them
through every step, saves every change, and then offers no way to finalise what they
just edited.

It sits directly on top of W-0023 and W-0046, which exist precisely so that gifts
become bequests. Both are satisfied at the moment of completion and neither survives
the first edit afterwards.

### Repro

**Part A — a gift can never be re-synced.**

1. Premium married account with a will document in `draft`.
2. `/estate/will-builder` → Gifts → add a gift → Continue to Review →
   **Complete & Finalise** (`POST /api/estate/will-builder/{id}/complete → 200`).
   Confirm the `Bequest` row exists with `will_document_id` set.
3. **View Will** → **Edit Will** → Continue through every step, changing a gift.
   Each step returns `PUT … → 200`.
4. Arrive at Review. **No "Complete & Finalise".** The changed gift is in
   `will_documents.specific_gifts` and nowhere else.

**Part B — deletion diverges permanently.**

5. `/estate/will-builder` → Specific Bequests → **Delete** a row that carries a
   `will_document_id` → confirm. `DELETE → 200`, row soft-deleted.
6. **View Will**. The will still recites the deleted gift.
7. There is no control that re-runs the sync. `/m/app/estate/bequests` shows the
   reduced list; the will shows the full one.

### Acceptance

1. A user who has finalised a will can bring their gifts and their bequests back into
   agreement through the interface. Any of these is acceptable, and the choice is a
   product call rather than a technical one:
   - re-finalising is reachable on a completed document (the Review step offers it
     again once anything has been edited); or
   - editing a completed document returns it to `draft` and the user re-finalises; or
   - the sync runs on save as well as on completion, so the rows always match the
     document.
2. Whichever is chosen, it is **one mechanism**, and the deletion path uses the same
   one (Rule 20). Deleting a document-sourced bequest either also removes the gift from
   the document, or is refused with an explanation pointing at the will builder — it
   must not silently produce two records that disagree.
3. The charitable total and the Inheritance Tax rate are shown to follow the change
   end to end, not just the row count.
4. Verified in a browser on web and on `/m`'s bequests screen, on a completed will —
   not on a draft, which is where every existing test for this behaviour sits.

### Notes

- Batch B's §2c prediction was reasonable and is right about the *service*: calling
  `markComplete()` again does recreate the row, and `syncBequests()` deliberately
  `forceDelete`s and rewrites every `will_document_id`-bearing row. It is only
  unreachable from the interface. Both halves matter — an API consumer or a future
  "re-finalise" control would see the recreate behaviour immediately.
- The tester deliberately did **not** call the complete endpoint directly to force the
  recreate. What a user can do is the question this item is about.
- Related but distinct: `will_documents.15` is the mirror generated from
  `will_documents.14` under W-0053's fix, which worked correctly — see
  `tests/Persona/20-08-2026_run/reports/R-16-batch-b-regression.md`.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`, and the two obvious re-entry routes
  were both checked rather than assumed.**
  1. `WillBuilderReviewStep.vue:99` gates the "Complete & Finalise" button on `v-if="!isComplete"`,
     so the control disappears the moment the document completes — W-0053's `?view=document` route
     reaches Review, but Review no longer offers the action.
  2. `WillDocumentService::syncBequestsForDocument()` (:754) is public and would do the job, but
     `grep` across `app/` and `routes/` finds exactly one caller:
     `app/Console/Commands/BackfillWillBequests.php:98`. There is no HTTP route to it.

  So the batch-B prediction remains right about the service and wrong about the user: the sync is
  reachable from a console command and from nothing a user can press. A completed will and the
  bequests the application says it holds can still disagree with no route to reconcile them.
  Unchanged since 2026-08-22.
