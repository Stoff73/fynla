# R-16 — Batch B estate regression, browser-verified

**Agent:** `persona-passA3` (third tester on the `peak_earners` run, seeded from R-14)
**Date:** 2026-08-21 · **Environment:** local `localhost:8000` only. csjones and
production untouched.
**Subjects:** Priya Raman `users.id 20` (premium, married) and Arjun Raman
`users.id 30` (premium, her spouse). Both driven in a visible Chrome window via
Playwright, real pointer clicks throughout.

**Purpose:** close Rule 14's loop on the four estate items `fix-batch-B` completed but
did not browser-verify, plus W-0037. Fix agents do not verify their own work; this is
the independent pass.

---

## Verdict at a glance

| # | Check | Verdict |
|---|---|---|
| 1 | Inheritance Tax cache — change a bequest, the displayed rate moves | **GREEN on the behaviour, but not for the reason the item assumes** — and the *rate label* is wrong on both estate screens. Two new defects: **W-0131**, **W-0132** |
| 2 | `WillController::deleteBequest()` — success reported as success, row actually gone | **GREEN** |
| 3 | Gift → `Bequest` sync with gifts actually present | **GREEN** — two gifts, two rows, correct amounts, conditions and types, visible on web and `/m` |
| 3b | Batch B's "delete a bequest and re-completion recreates it" | **Not reproducible from the interface — and the reason is worse.** Completion is a one-way door: **W-0133** |
| 4 | W-0053 — mirror generatable after completion, across a reload; `will_documents.14` rescued | **GREEN** — rescued through the interface, `mirror_document_id = 15` |
| 5 | W-0037 — bequest priority | **RED, as predicted.** Live evidence appended to the board item |

---

## Done

### Check 1 — the Inheritance Tax cache

**What I was asked to prove:** change a bequest and confirm the displayed rate moves
with no other edit — "the check that proves the cache, not the calculation".

**The behaviour is green.** Driven on Priya, whose estate is unusually well suited to
this: property £715,000, no liabilities, married, so the combined Nil Rate Band is
£650,000 and the charitable baseline is £65,000 — a 10% threshold of £6,500, which her
£10,000 Cancer Research UK legacy clears. The displayed figure moved in both
directions with nothing else touched:

| State | Taxable estate (age 84) | Inheritance Tax shown | Implied rate |
|---|---|---|---|
| Legacy recorded | £1,104,585 | **£397,651** | 36% |
| Legacy deleted | £1,134,437 | **£453,775** | 40% |
| Legacy re-added | £1,104,585 | **£397,651** | 36% |

£397,651 ÷ £1,104,585 = 0.36 exactly; £453,775 ÷ £1,134,437 = 0.40 exactly. Server
figures confirmed independently in tinker (`projected_iht_liability 397650.53`).
Screenshots `46`, `49`, `52`.

**But it does not prove the cache, because there is no cache.** → **W-0131**.
`IHTCalculationService::calculate()` persists only when `$persist === true` (`:83`,
`:257`) and **no caller in `app/` passes it** — `grep -rn "persist: true" app/` returns
one hit and it is the docblock at `:68`. `saveCalculation()` is the table's only
writer, and `iht_calculations` holds **0 rows for every user in the database**, before
and after a full calculation. `getCachedCalculation()` therefore returns null on every
request and every estate view recomputes in full. Batch B's
`charitableBequestFingerprint()` (`:1535`) is correct and should stay — it is simply
unreachable, and becomes load-bearing the moment anyone turns persistence on.

**And the rate the user is shown is not the rate the figure was calculated at.**
→ **W-0132**, severity high. `/estate/inheritance-tax` renders

```
Taxable Estate                      £0     £1,104,585
Inheritance Tax Liability (40%)     £0       £397,651
```

where £397,651 *is* 36% of £1,104,585. The label comes from
`IHTPlanning.vue:916-918` — `this.charitableBequest ? '36%' : '40%'` — reading
`users.charitable_bequest`, a flag the client never receives: the user object in
`fynla-state` has no `charitable_bequest` key at all, so the label is permanently 40%
regardless of the will. Writing the flag works (`PUT → 200`, database `true`); reading
it back does not, so after a reload both radios are unchecked again. In-session, before
any reload, answering "Yes" makes the frontend abandon the server's figure and
recompute with an **assumed** donation of 10% of baseline — £148,444 at age 84, giving
£956,141 / £344,211 — matching neither the server nor Priya's actual £10,000 legacy.
Screenshots `65`, `66`, `67`, `68`.

This is W-0020's own journey failing at the last step: the rate now moves in the
calculation and the user is still told 40%.

### Check 2 — `deleteBequest()`

**GREEN.** The old fault was a `noContent()` return against a declared `: JsonResponse`,
so the row deleted and the response then threw — the user saw an error for a success.

Driven three times, twice on Priya and once on Arjun, always by clicking **Delete** and
accepting the confirmation:

- `DELETE /api/estate/bequests/54 → 200`, banner **"Bequest deleted successfully"**
  rendered within 250ms (`53-web-priya-bequest-delete-success-banner.png`).
- `DELETE /api/estate/bequests/55 → 200`, same banner on Arjun
  (`62-web-arjun-delete-document-sourced-bequest-success.png`).
- Rows confirmed soft-deleted in MySQL each time (`52` at 18:40:39, `53` at 18:45:46,
  `54` at 18:47:11, `55` at 18:56:43). No error banner appeared in any run.

Both branches covered: a hand-made row (`will_document_id` NULL) and a
document-sourced row (`will_document_id = 15`).

### Check 3 — gift → `Bequest` sync, with gifts actually present

**GREEN, on a real user journey rather than an API probe.**

Priya's own document could not be used — it was already complete, and completion cannot
be repeated (see check 3b). The clean route was Arjun: the mirror generated in check 4
lands as `status: draft` (`WillDocumentService.php:403`), so he could add gifts and
finalise as a first-time user would.

Two gifts entered on the Gifts step, then Continue through Residuary, Funeral and
Digital to Review, then **Complete & Finalise**
(`POST /api/estate/will-builder/15/complete → 200`). Result:

```
bequests
  55  Meera Raman         specific_amount  £5,000   will_document_id 15  priority 1
      conditions: "Receive at age 25, held in trust"
  56  Cancer Research UK  specific_amount  £10,000  will_document_id 15  priority 2
```

Both render on the Estate screen with amounts and conditions
(`61-web-arjun-will-planning-synced-bequests.png`), and the `/m` bequests screen —
which reads the same `GET /api/estate/bequests` — shows them too
(`69-m-arjun-bequests-synced-from-will-document.png`). Zero-gift verification-by-absence
is now replaced by real coverage.

### Check 3b — batch B's unraised behaviour

Batch B reported, without raising it: *"deleting a bequest carrying a
`will_document_id` removes the row, but a later re-completion recreates it from the
document's gifts."*

**From the interface it does not come back, because there is no later re-completion.**
→ **W-0133**, severity high.

`WillBuilderReviewStep.vue:100` gates **Complete & Finalise** behind `!isComplete`, and
`isComplete` is `formData.status === 'complete'`, seeded from the document. Editing
saves via `PUT`, which never changes `status` and never syncs. Driven end to end: from
Arjun's completed will, **Edit Will** → Continue through **all eight** steps, each
saving `200` → back at Review with only **Edit Will** and **Print / Save PDF**
(`64-web-arjun-review-after-full-edit-walk-no-complete-button.png`).

The consequence is a permanent divergence the user cannot repair. After deleting
bequest `55`:

| Surface | Shows |
|---|---|
| The will itself (View Will) | "(a) The sum of £5,000 to Meera Raman… (b) The sum of £10,000 to Cancer Research UK." |
| Estate → Specific Bequests | Cancer Research UK only |
| `/m` → Specific bequests | "**1 BEQUEST** — Cancer Research UK £10,000" |

`will_documents.15.specific_gifts` still holds both gifts. Since W-0020 the Inheritance
Tax rate follows the `Bequest` rows, so the same move on a *charitable* row would drop
the legacy out of the tax calculation while the will still recites it — silently, with
no way back.

Batch B's prediction is right about the service: `syncBequests()` does `forceDelete` and
rewrite every `will_document_id`-bearing row, so calling `complete` again would recreate
it. I deliberately did not call the endpoint directly — what a user can do is the
question.

### Check 4 — W-0053, and the rescue of `will_documents.14`

**GREEN, including the rescue.**

- Fresh navigation to `/estate/will-builder` → **View Will** → the wizard opens at
  Review for the completed document, and **"Generate Spouse's Will" is present**
  (`54-web-priya-view-will-review-generate-spouse-present-W-0053.png`).
- **It survives a hard reload** of `?view=document` — re-checked after `page.reload()`,
  still present. That is the specific thing the fix changed: the button now consults
  `mirrorGenerated()` (`mirrorData || formData.mirror_document_id`) rather than
  in-session state.
- Clicking it: `POST /api/estate/will-builder/14/mirror → 200`, and the button
  correctly disappears once the pair exists.
- **`will_documents.14` is no longer stranded.** `mirror_document_id = 15`, and
  `will_documents.15` exists on `user_id 30` as `will_type: mirror`, `status: draft`,
  with the reciprocal `mirror_document_id = 14`.
- The W-0024 swap is correct in the generated mirror: Arjun's executor is **Priya
  Raman ("my Spouse")**, not himself; residuary 100% to Priya; the third-party guardian
  **Nisha Raman** kept her name and her recorded relationship.
- On Arjun's Review the Generate button is correctly **absent**, because his
  counterpart already exists.

Batch B's note that **Edit** does not reopen the wizard is confirmed — the route back
is **View Will**, exactly as documented.

### Check 5 — W-0037, bequest priority

**RED, as predicted.** Full live evidence appended to
`workforce/ops/board/W-0037-bequest-form-cannot-record-priority.md`. In short: neither
entry path can express priority. The Estate bequest form offers four controls and none
of them is priority; the will-builder gift form has no priority field either, so
`syncBequests()` assigns `priority_order` from array order. Entering the child before
the charity produced child = 1, charity = 2 — the reverse of the persona's intent, and
the intent had nowhere to be typed. Priority is displayed nowhere: `grep -n "priority"`
is empty in the bequest form, the Will Planning screen and the `/m` bequests view.
"Cancer Research UK" was again written as `beneficiary_type: "individual"`, counting as
charitable only because `isCharitable()` finds "cancer" in the name.

One of W-0037's acceptance criteria **is** now met and should not be redone: gifts do
arrive as `Bequest` rows (W-0023), verified live. They arrive without priority.

---

## Not done, and why

- **iOS.** Not attempted. The native app reads the csjones staging database and this
  task is local-only. **I COULD NOT TEST THIS** on iOS.
- **csjones / dev leg.** Out of scope for this task; no PR, no deploy, nothing pushed.
- **`/m` beyond the bequests screen.** Only `/m/app/estate/bequests` was checked, as
  parity evidence for check 3. `/m` has no will builder and no Inheritance Tax rate
  readout (`resources/mobile/router.js:69-70` registers only `/estate` and
  `/estate/bequests`), so W-0132 has no `/m` surface to fail on today. Not verified
  beyond that.
- **The charitable-rate flip using an oversized temporary legacy** (the playbook's
  W-0020 part (b)). Not needed here — Priya's real £10,000 legacy crosses her real
  £6,500 threshold, so the rate was exercised on genuine persona data in both
  directions. No invented figures were entered.
- **Priya's `users.charitable_bequest` is not restorable to its original `NULL`.** It
  was `NULL` at the start of this run and is `true` now, set through the interface
  while evidencing W-0132. The card offers only Yes/No, so `NULL` cannot be reached
  from the interface, and testers do not patch database rows. It has no server-side
  effect — `determineIHTRate()` reads `IHTProfile.charitable_giving_percent`, not this
  column. Flagged to the coordinator.
- **Arjun's password changed, necessarily.** His first login forced
  "Change Your Password" before continuing — an app-mandated step for an
  app-created spouse account, not a defect. It is now **`Password2!`**
  (`pt.throwaway.spouse+0821@example.com`). Priya remains `Password1!`. Screenshot
  `56-web-arjun-forced-password-change.png`.

---

## Assumptions

- **The estate is modelled as the household second-death estate**, which is what
  `is_married: true` + `data_sharing_enabled: true` produce for these two accounts. All
  arithmetic above is on that basis.
- **Arjun's mirror was safe to generate.** The task named the rescue of
  `will_documents.14` as a required check, and generating is irreversible from the
  interface (`generateMirrorWill()` returns the existing counterpart on a second
  press). The coordinator was told the plan before it ran and provisioned Arjun premium
  in response.
- **`GET /api/estate/bequests` rendering on the web Estate screen is the same endpoint
  `/m` calls** — confirmed in `resources/mobile/views/modules/EstateBequests.vue:87`
  and then confirmed again by loading the `/m` screen itself.
- The £10,000 / £5,000 gift amounts entered on Arjun's will are test values chosen to
  exercise the sync, not persona figures. `peak_earners.md` is the contract for the
  `peak_earners` household; Priya and Arjun are throwaway fixtures created by earlier
  testers for estate work.

---

## Needs

1. **A routing decision on W-0132 and W-0133** — both sit squarely on batch B's W-0020
   and W-0023 territory and are arguably the last mile of those items rather than new
   ground.
2. **A decision on W-0131** — cache on, or cache deleted. It is not the tester's call.
3. **Confirmation on Priya's `charitable_bequest = true`** — leave it, or the
   coordinator nulls it.
4. Nothing blocking. The tester is not idle.

---

## Noticed — outside this task's remit, routed here rather than fixed

- **Hardcoded tax presentation values.** `IHTPlanning.vue:916-918` hardcodes the
  strings `'36%'` and `'40%'`, and `IHTCalculationTable.vue:558` defaults
  `effectiveIHTRateLabel` to `'40%'`. The server has
  `TaxConfigService::getCharitableReducedRate()` and the API returns
  `iht_rate_percent`. A Rule 2 breach masked by the numbers happening to be right this
  year. Recorded inside W-0132; `tax-compliance-reviewer` may want it separately.
- **`/plans/estate` says "Estate Plan Not Applicable"** for a household with a
  £2.1m projected estate and a £397,651 projected Inheritance Tax bill, because its
  *current* liability is £0 (`47-web-priya-plans-estate-not-applicable.png`). Arguably
  correct by its own rule, but it withholds the estate plan from exactly the household
  that has a decade to act on it. Product call, not raised as a defect.
- **One login attempt silently reset the form.** Priya's first sign-in returned
  `POST /api/auth/login → 200` and issued a verification code
  (`email_verification_codes.26`), yet the interface returned to an empty login form
  rather than the code screen (`45-web-priya-after-signin-click.png`). The immediate
  retry worked and it did not recur across three further logins, so it is recorded, not
  raised — but a login that succeeds server-side while the user is shown the form again
  would be a bad first impression, and the code it burns counts against the resend cap.
- **The estate-planning "readiness" panel counts domicile as outstanding** for a user
  whose completed will document has `domicile_confirmed: england_wales`. Priya shows
  "OUTSTANDING (2): Your domicile status, Your investments". Not investigated.

---

## Evidence

Screenshots in `tests/Persona/20-08-2026_run/pass-a-web/`, numbers 44–69:

| Range | Covers |
|---|---|
| 44–45 | Priya login, and the silent form reset noted above |
| 46–47 | The 40% label against the 36% figure; `/plans/estate` unavailable |
| 48–53 | Bequest delete and re-add, both success banners, the rate moving both ways |
| 54–55 | W-0053: Generate present after reload; mirror `will_documents.15` created |
| 56–60 | Arjun: forced password change, resumed mirror draft, two gifts, completion |
| 61–64 | Synced bequests on screen; delete; the will still reciting the deleted gift; Review with no Complete & Finalise after a full edit walk |
| 65–68 | The charitable card and the toggle: three different figure sets for one estate |
| 69 | `/m` bequests screen showing the synced row |

`VOID-43-web-iht-after-bequest-cache-check-unreported-predecessor.png` — taken by the
previous tester at 14:25:41, after R-14 and R-15, with no report describing it. Its
state cannot be attested to, so it is voided rather than left in the sequence as though
it evidenced something. Two of my own were renamed after capture, because their first
names described what I expected rather than what the screen showed (`67`, `68`).

**Board items:** `W-0131`, `W-0132`, `W-0133` created; `W-0037` extended with live
verification.
