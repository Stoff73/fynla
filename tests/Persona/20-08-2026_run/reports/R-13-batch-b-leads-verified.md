# R-13 — Batch B's leads independently verified; the screenshot captured; one new defect

**When:** 2026-08-21 16:20–17:05 · **Surface:** desktop web, `localhost:8000`
**Driver:** Playwright MCP, real pointer clicks, isolated context.
**Account:** throwaway `users.id 20` Priya Raman, **premium**, married, linked spouse
`users.id 30`, minor child Meera. David (16) and Sarah (17) untouched.

---

## Done

### The screenshot that only existed as an accessibility snapshot

**`38-web-will-review-error-banner-and-disabled-button-W-0024.png`** — one image
carrying the whole proof:

- Step **9 Review** active in the wizard nav.
- The "Please Review" panel reading **"A will cannot appoint its own testator as
  executor. Name the person who will carry out your wishes."**
- The will preview beneath it rendering **"I APPOINT Priya Raman … (my Self) to be the
  sole Executor and Trustee"** — the offending state, visible in the document.
- **Complete & Finalise greyed out** — `disabled: true`, `opacity: 0.5`,
  `cursor: default`.

The earlier attempt (`37-…`) caught the button but not the banner: they sit 910px apart
in a 673px viewport. Enlarging the viewport to 1440×1500 put both in one frame. Batch B's
`browser_take_screenshot` timeouts were an animation problem — `animations: 'disabled'`
with a longer timeout captures it reliably.

### The validation gates both ways — a two-sided check, not a one-sided one

| State | Review panel | Complete & Finalise |
|---|---|---|
| Executor = "Priya Raman" (the testator) | error, plus "children under 18 but no guardian" | **disabled**, opacity 0.5 |
| Executor = "Arjun Raman" (spouse), guardian appointed | only an informational "consider a backup executor" | **enabled**, opacity 1 |

The will then completed cleanly: `POST /api/estate/will-builder/14/complete` → 200,
`will_id 23`, `will_type: mirror`, `status: complete`, and
`wills.executor_name = 'Arjun Raman'` — regenerated, not the testator.

Guardians were offered and recorded (`Nisha Raman`, Sister), and the Guardians step
correctly identified the minor child as "Meera Raman (born 20 September 2011)".

### W-0019 — partial confirmation

The will builder shows a **"Mirror Wills Only"** heading on the intro step for this
married user, and the phrase "simple will" appears nowhere in the flow. Consistent with
the W-0019 fix. **I did not test the API-level `will_type: "simple"` → 422**, so that
half of Batch B's lead is still theirs, not independently confirmed by me.

### W-0053 — new defect (high)

**Completing a mirror will strands the user.** "Generate Spouse's Will" exists **only**
on the Review step, before completion. After **Complete & Finalise** there is no route
to it:

- `/estate/will-builder` → Edit · Add Bequest · Account. No Generate.
- `/estate` → no generate/mirror control at all.
- **Edit** does not reopen the wizard — it stays on "Will Planning".
- `will_documents.14.mirror_document_id` remains **NULL**; the spouse's will never exists.

Since W-0019 makes mirror wills the **only** option for a married user, the sole
available will path breaks its own pair at the final step, with no warning.

**This does not contradict Batch B.** Their mirror generation is real and correct —
reachable by clicking Generate *before* Complete & Finalise. The defect is that the
ordering is undiscoverable and irreversible. Batch B verified the order that works; I
took the other obvious order and it stranded.

---

## Not done, and why

- **The mirror itself was never generated**, so I have **not** independently confirmed
  Batch B's central claim — that the generated mirror names Priya as Arjun's executor
  where it previously named Arjun himself. W-0053 is what blocked it. Re-testable by
  building a second will and clicking Generate before completing.
- **Bequests — corrected 2026-08-21 (team-lead).** I listed the gift → `Bequest` sync as
  "untested". That is wrong: I entered **no gifts**, so `bequests` for will 23 being **0**
  is **correct behaviour, verified by absence** — not a gap and not a W-0023 failure.
  What remains genuinely untested is the sync with gifts **present**, which needs a will
  built with at least one gift.
- **W-0019's API-level 422** on `will_type: "simple"` — untested.
- **The letter, trust surfaces, and Fyn's will-intent layer** — all untested. Trust
  *presentation* is held anyway pending `design-palette-fix`.
- **Cap-lift test** — still blocked; see R-12 and my message. Needs either user 20
  reverted to free or a second free throwaway.

---

## Assumptions

1. Using premium Priya rather than David/Sarah for the will build is correct — David and
   Sarah already hold completed wills (which is why Batch B created its own pair), and
   they are batch reproduction data.
2. W-0053 is about **ordering and reachability**, not about the mirror generator itself,
   which Batch B exercised successfully. I have not proven the generator is correct.

---

## Needs

1. **Cap-lift unblock** — revert user 20 to free, or a second throwaway. My preference
   remains a second throwaway so Priya keeps premium.
2. **W-0053 triage** — it may be a small fix (surface the control post-completion) or a
   flow decision (warn before completing). The second is a product call.

---

## Noticed

- The will builder is a **10-step** wizard: Intro, Personal, Executors, Guardians,
  Gifts, Residuary, Funeral, Digital, Review, Signing. The playbook's §1.9 describes
  the steps loosely; I will tighten it with the real step list and the fact that the
  Review step carries **three** terminal actions — Generate Spouse's Will, Print/Save
  PDF, Complete & Finalise — whose ordering matters (W-0053).
- The residuary rendered correctly as "As to **100%** thereof to **Arjun Raman**
  absolutely", picking up the linked spouse without being told.
- This app hydrates slowly — several reads that looked like blank pages were simply
  early. I now wait 9–11s on estate routes before believing a blank. Worth stating in
  the playbook so the re-run does not log phantom blank-page defects.

---

## Context position

Roughly **675k**. Inside the Rule 22 buffer; I will flag well before 900k.
