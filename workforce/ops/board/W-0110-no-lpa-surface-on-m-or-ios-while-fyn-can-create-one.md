---
id: W-0110
title: There is no Lasting Power of Attorney surface on /m or iOS, yet Fyn can create one from both — a record the user can never see again
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [product-lead]
status: done
severity: medium
surfaces: [m, ios]
created: 2026-08-21T17:49:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0044 (native app has no estateWill handoff case), W-0100 acceptance 4]
prior_art_outcome: extend
constitution_refs: [05-perimeter]
source: W-0100 acceptance 4, fix-batch-G, 2026-08-21
---

## Intent

**Answering W-0100 acceptance 4: the Lasting Power of Attorney exists on web only, and
that is worse than it sounds, because Fyn writes to it from everywhere.**

Verified 2026-08-21:

- **Web** — `/estate/power-of-attorney` and `/estate/lpa/create/:type`
  (`resources/js/router/index.js:961,975`), behind the `estate.full` premium gate.
- **`/m`** — nothing. `resources/mobile/` contains no match for "attorney", "Lpa" or
  "power of attorney" outside unrelated test fixtures. `resources/mobile/router.js`
  has `/estate` and `/estate/bequests` and no Lasting Power of Attorney route.
- **iOS native** — nothing. `ios-native/Fynla` contains no match.
- **`WebHandoffDestination`** (`app/Enums/WebHandoffDestination.php`) has an
  `ESTATE_WILL` case and **no Lasting Power of Attorney case** — the same absence
  W-0044 found for wills, confirmed for this instrument rather than assumed.

**The part that makes it a defect rather than a parity gap.** `CoordinatingAgent`
implements `create_power_of_attorney` and `update_power_of_attorney`
(`app/Agents/CoordinatingAgent.php:4253`, `:4328`), registered in both tool
catalogues (`AiToolDefinitions.php:103-104`, `XaiToolDefinitions.php:99-100`) and
stripped only from the read-only advice surface (`AdviceFyn.php:164`). Fyn's write
state reaches every surface through the one endpoint.

So a user on `/m` or on the native app can tell Fyn about their Lasting Power of
Attorney, have a `lasting_powers_of_attorney` row and its attorneys created, get
"Recorded your Property & Financial Affairs Lasting Power of Attorney" — and then have
no screen on that device that shows it, edits it, or prints it. The write works and
the read does not exist.

Note the persona carries **Has LPA: Yes**, so a persona pass on `/m` or native will
walk into this.

## Acceptance

1. Decide the intended shape: build the `/m` and native surfaces, or add a web handoff
   (`WebHandoffDestination`) so the user is taken somewhere that works, or gate the
   Fyn write tools to surfaces that can display the result. **Do not leave a write
   with no read.**
2. **Recommend the handoff first** — it is small, it matches the agreed architecture
   for `/m` handing off to web, and it removes the dead end today. Building the full
   surfaces is a separate, larger piece of work.
3. Whatever is chosen, `WebHandoffDestination` gains its case and W-0044's finding is
   re-checked at the same time — one absence, two instruments.
4. Rule 19: state explicitly which surfaces end up covered.

## Working notes

- 2026-08-21 fix-batch-G: this answers W-0100 acceptance 4 in full. Verified by
  absence-grep across all three client trees, not assumed from W-0044.

---

## Closed 2026-08-31 — the handoff, plus the vocabulary consolidated

Acceptance 2 said recommend the handoff first, so that is what was built.

**Acceptance 1 — the write no longer has no read.** `/m`'s estate screen now reads
`GET api/estate/lpa`, the same endpoint the web store reads, and shows every recorded
instrument with its status:
`resources/mobile/views/modules/Estate.vue:117-137` (the card),
`:181-188` (`openLpaOnWeb`), `:216-220` (the fetch). A household with none recorded is
told so in words rather than shown an empty card.

**Acceptance 3 — `WebHandoffDestination` gains its case.**
`app/Enums/WebHandoffDestination.php:25-29,42` — `ESTATE_LPA` → `/estate/power-of-attorney`,
the same shape as `ESTATE_IHT`.

**Rule 20 — the vocabulary had already drifted, so consolidating it was part of the
fix.** Four web components each carried their own copy of the instrument's name, and
they did not agree: `IHTPlanning.vue` said "Property & Financial" where the summary and
detail cards said "Property & Financial Affairs". A fifth copy on `/m` would have made
it five. The label is now served with the record:
`app/Models/Estate/LastingPowerOfAttorney.php:157-190` (`type_label`, `status_label`,
`$appends` at `:29-37`), read by `LpaSummaryCard.vue:95-101`, `LpaDetailView.vue:100-106`,
`IHTPlanning.vue:843-852`, `PowerOfAttorneyTab.vue:125` and `/m`.

### Tests — the diff only

- `tests/Feature/Estate/LpaControllerTest.php` — 2 new (payload carries both labels).
  **Mutation-verified:** emptying `$appends` turns both red.
- `tests/Feature/Auth/WebHandoffTest.php` — 15 passing, including the new
  `estate_lpa` handoff and the updated allowlist guard.
- `resources/mobile/views/modules/__tests__/EstateLpa.spec.js` — 3 new (read-back,
  the empty case, the handoff call).
- Regression: 24 web Estate specs, 28 LPA PHP tests, 47 Fyn-tool and compliance tests.

## Not done — iOS, deferred

CSJ ruled on 2026-08-31 that the board loop is web and `/m` only. So:

- **The native mirror of `WebHandoffDestination` was not updated.** The allowlist
  guard at `tests/Feature/Auth/WebHandoffTest.php:173-186` records this explicitly at
  the line rather than hiding it. Native cannot send a case it does not have, so the
  residue is a missing route on native — the same gap as **W-0044**, and it should be
  picked up with it.
- **Native has no LPA read surface**, unchanged by this item.

**Surfaces covered (acceptance 4):** web ✔ (already), `/m` ✔ (new), iOS ✘ (deferred).
