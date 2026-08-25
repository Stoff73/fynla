---
id: W-0037
title: Bequest form cannot record priority order, beneficiary type or charity registration — charitable status is inferred from the beneficiary's name
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T12:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0020, W-0023]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Scope correction and severity raise — coordinator, 2026-08-21

**This item absorbs `tax-compliance-reviewer`'s F9. They are one defect, not two** —
same form, same request, same `Bequest` write path. Splitting them would put two
changes to one mechanism into two items, which is the shape Rule 20 exists to prevent,
and both need the same round-trip proven end to end.

**Severity raised medium → high**, on F9's rationale: name-substring matching is not a
fallback in production, it is the **only live mechanism** — both structured checks are
unreachable because no write path populates `beneficiary_type` or
`charity_registration_number`. It fails silently in both directions on a decision that
changes the Inheritance Tax rate for the whole estate. The priority-order half is
genuinely medium; the item carries the higher of the two.

**Scope widened — this item must fix all four hops, not one form.** As originally
written it named only `BequestForm.vue`, and a reasonable implementer would have fixed
that and stopped, leaving every will-builder gift still falling through to
name-matching:

1. `BequestForm.vue` — the Estate bequest form.
2. **The will-builder gift step** — a different form entirely.
3. **`SaveWillDocumentRequest.php:49-54`** — accepts only `beneficiary_name`, `type`,
   `amount`, `description`, `conditions` on a gift. No charity field, so
   `syncBequests()` physically **cannot** set `beneficiary_type`.
4. `syncBequests()` — must carry the recorded type through.

**A stale bullet to disregard:** the original text says this would be "fixed alongside
W-0023". W-0023 has since landed and closed **without** covering it, so read literally
that bullet is already satisfied and misleads. The four hops above are the scope.

**Now cheaper than when raised:** `isCharitable()` has a single home — the drifted
duplicate in `WillAnalysisService` was deleted during W-0020 — so "prefer the recorded
type, keep the keyword match as a legacy fallback" is one method to change, not two.

## Intent

Found by: persona run `peak_earners`, playbook preparation. Local `localhost:8000`,
premium. Accounts **David Jones (16)** and **Sarah Jones (17)**.

**Surface:** `/estate/will-builder` → `WillPlanning.vue` → `BequestForm.vue`.

**Overlaps Batch B.** W-0023 (will-builder gifts never become `Bequest` rows) and
W-0020 (charitable total tested a nonexistent enum) are both in flight on this same
data. This item is the third gap in the same record and should be fixed with them.

### Expected

The persona gives a **Priority** for every one of its six bequests
(`tests/Persona/peak_earners.md`, both Will sections):

| Beneficiary | Type | Amount | Priority |
|---|---|---|---|
| Cancer Research UK / British Heart Foundation | Specific Amount | £10,000 | **1** |
| William Jones | Percentage | 50% | **2** |
| Charlotte Jones | Percentage | 50% | **2** |

Priority is what tells the executor the order gifts are satisfied in when the estate
cannot meet them all — a £10,000 charitable legacy at priority 1 is paid before the
residuary split. `bequests.priority_order` exists to hold it.

A bequest to a charity should also be recorded **as** a charity, deliberately, since
that is what moves the Inheritance Tax rate from 40% to 36%.

### Actual

`BequestForm.vue` exposes six inputs and no more:

- `beneficiary_name`
- `bequest_type`
- `percentage_of_estate`
- `specific_amount`
- `specific_asset_description`
- `conditions`

There is **no priority input**, so all six persona bequests save at whatever default
`priority_order` the backend applies, and the persona's stated ordering cannot be
entered. There is no `beneficiary_type` selector and no
`charity_registration_number` input either, though both columns exist and
`beneficiary_type` is an enum of `individual` / `charity` / `trust` / `organization`.

Because nothing records that a beneficiary is a charity, `Bequest::isCharitable()`
(`app/Models/Estate/Bequest.php:87-110`) falls through to **matching the beneficiary's
name against a keyword list** — "charity", "charitable", "foundation", "cancer", and
so on.

That happens to work for this persona: "Cancer Research UK" matches on *cancer*,
"British Heart Foundation" on *foundation*. It would **silently fail** for a charity
whose registered name contains none of the keywords — Shelter, Mind, Barnardo's,
Scope, Sightsavers, Oxfam — and would **silently misfire** for an individual whose
surname happens to match, or a legacy to a person named in memory of a cancer charity.
A wrong answer here changes the Inheritance Tax rate on the whole estate, and it does
so without ever telling the user which way it decided.

### Repro

1. Premium account with a spouse and children.
2. `/estate/will-builder` → the bequests section → Add a bequest.
3. Search the form for a priority, beneficiary-type or charity-number input — there is
   none.
4. Save the persona's three bequests; `bequests.priority_order` does not reflect the
   persona's 1 / 2 / 2, and `beneficiary_type` is not set to `charity` for the legacy.
5. Change the charity to "Shelter" and re-check `Bequest::isCharitable()` — it returns
   `false`, and the charitable total drops to zero.

### Evidence

- `resources/js/components/Estate/BequestForm.vue` — the complete set of `v-model`
  bindings; no priority, no beneficiary type, no charity number
- `app/Models/Estate/Bequest.php:87-110` — `isCharitable()` and its keyword list
- `bequests` schema — `priority_order`, `beneficiary_type`
  (`enum('individual','charity','trust','organization')`) and
  `charity_registration_number` all present
- `resources/js/components/Estate/WillPlanning.vue` — the only host of `BequestForm`
- Persona lines: `tests/Persona/peak_earners.md`, David's Will and Sarah's Will
  bequest tables, Priority column

## Acceptance

- [ ] The bequest form accepts a priority order and persists `priority_order`;
      entering the persona's 1 / 2 / 2 produces those values.
- [ ] The form records beneficiary type explicitly, so a charitable legacy is marked
      as charitable rather than guessed from its name.
- [ ] A charity registration number can be recorded where the user has one.
- [ ] `isCharitable()` prefers the recorded type; the name-keyword match is retained
      only as a fallback for legacy rows, and its limits are documented.
- [ ] A bequest to "Shelter" recorded as a charity counts toward the charitable total.
- [ ] Priority is surfaced where it matters — the will document prose and the estate
      view should reflect the order gifts are satisfied in, not just store it.
- [ ] Fixed alongside W-0023 so that will-builder gifts arrive as `Bequest` rows
      **carrying** priority and beneficiary type — one path, one shape (Rule 20).
- [ ] `/m` bequests screen and iOS show priority (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

- 2026-08-21 build-lead (Batch B): folding in `tax-compliance-reviewer`'s **F9**,
  which team-lead has ruled is this item's root cause seen from the other end —
  one item, not two. Cross-referenced from W-0020.

  **F9's finding, verbatim in substance:** charity detection is not merely
  missing a fallback — **name-substring matching is the ONLY live mechanism in
  production.** Both structured checks in `Bequest::isCharitable()` are
  unreachable, because no write path populates either column:
  - `BequestForm.vue:214-222` sends `beneficiary_name`, `bequest_type`,
    `percentage_of_estate`, `specific_amount`, `specific_asset_description`,
    `conditions` — and nothing else.
  - `SaveWillDocumentRequest.php:49-54` accepts only `beneficiary_name`, `type`,
    `amount`, `description`, `conditions` on a will-builder gift, so
    `WillDocumentService::syncBequests()` **physically cannot** set
    `beneficiary_type` however it is written.
  - `PreviewUserSeeder.php:1419-1428` does not set them either.

  So every charitable determination that moves an Inheritance Tax rate is made by
  matching a free-text name against a keyword list. It fails silently in **both**
  directions: Shelter, Mind, Barnardo's, Scope, Sightsavers and the RNLI are all
  missed, while "Cancer Consultants Ltd" is a false positive.
  `tax-compliance-reviewer` rated it **High** on exactly that basis.

  **My addition, and the reason the item's scope needed widening:** the two forms
  are separate paths and fixing one leaves the other broken. This item's Actual
  section describes `BequestForm.vue` only; a reasonable implementer would fix it
  and stop, and every will-builder gift would keep falling through to name
  matching. Four hops need to carry the data end to end — `BequestForm.vue`, the
  will-builder Gifts step, `SaveWillDocumentRequest`, and `syncBequests()`.

  **On priority (this item's original half):** `syncBequests()` assigns
  `priority_order` sequentially from the order gifts appear in the document array,
  because the gift shape has no priority field. So the persona's ordering
  (charity 1, children 2) cannot round-trip even now the sync works. Same four
  hops, same fix.

  **One thing already done that makes this cheaper.** W-0020 deleted the drifted
  duplicate of this logic: `WillAnalysisService::isCharitableBequest()` was a
  near-copy of `Bequest::isCharitable()` that had already diverged — it treated
  **`'trust'`** as a charity indicator, so a "Smith Family Trust" counted toward
  the charitable total, which is the *unsafe* direction. `Bequest::isCharitable()`
  is now the single home and carries a docblock recording why `'trust'` must not
  return. The acceptance bullet "isCharitable() prefers the recorded type, keyword
  match as legacy fallback" is therefore one method to change, not two.

---

## Live verification, 2026-08-21 — persona-passA3, batch B regression pass

**Status confirmed UNFIXED, driven in the browser on two accounts. Board `queued` is
correct; nothing in Batch B's work changed this behaviour.**

**Both entry paths were exercised, and neither can express priority.**

1. **The Estate bequest form** (`/estate/will-builder` → **Add Bequest**), as Priya
   Raman `users.id 20`. Filled and submitted live — `POST /api/estate/bequests → 201`.
   The rendered form offers exactly four controls: Beneficiary Name, "What are you
   leaving them?", the amount/percentage/description field that follows from it, and
   Conditions. **No priority field, no beneficiary type, no charity number.**
   Screenshot: `50-web-priya-add-bequest-form-filled-no-priority-field.png`.
   The row written carried `priority_order: 1`, assigned server-side by
   `WillController::storeBequest()` (`:163-167`, max + 1), and
   `beneficiary_type: "individual"` for **Cancer Research UK** — a charity, recorded as
   an individual, counting as charitable only because `isCharitable()` finds the
   substring "cancer" in its name.

2. **The will-builder Gifts step**, as Arjun Raman `users.id 30`, completing the mirror
   will `will_documents.15`. Two gifts entered **deliberately in the reverse of the
   persona's intended priority** — the child first, the charity second — to test
   whether intent survives:

   | Entered | Beneficiary | Amount | Persona intent | `priority_order` written |
   |---|---|---|---|---|
   | 1st | Meera Raman | £5,000 | priority **2** | **1** |
   | 2nd | Cancer Research UK | £10,000 | priority **1** | **2** |

   `syncBequests()` assigns `++$priority` from array order
   (`WillDocumentService.php:578`), so the order is exactly the order the rows were
   typed in, and the persona's ordering has nowhere to live. Screenshot:
   `58-web-arjun-gifts-step-two-gifts-no-priority-field-W-0037.png`; rows
   `bequests.55` (priority 1) and `.56` (priority 2).

**Priority is not displayed anywhere either.** `grep -n "priority"` returns nothing in
`resources/js/components/Estate/BequestForm.vue`,
`resources/js/components/Estate/WillPlanning.vue`, or
`resources/mobile/views/modules/EstateBequests.vue`. Confirmed live: the Estate screen
lists the two bequests in priority order without ever naming the concept, and the `/m`
screen (`69-m-arjun-bequests-synced-from-will-document.png`) shows beneficiary, amount
and conditions only.

**One acceptance criterion is now satisfied by Batch B and should not be re-done:**
will-builder gifts *do* arrive as `Bequest` rows (W-0023), verified live — they simply
arrive without priority or beneficiary type. The remaining criteria stand unchanged.
