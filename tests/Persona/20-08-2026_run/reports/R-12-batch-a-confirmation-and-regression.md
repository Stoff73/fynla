# R-12 — Batch A independent confirmation: W-0014 cleared, a critical regression found

**When:** 2026-08-21 15:20–16:00 · **Surface:** desktop web, `localhost:8000`
**Driver:** Playwright MCP, real pointer clicks, isolated browser context.
**Account:** throwaway `users.id 20` Priya Raman, **free tier**, linked spouse
`users.id 30`. David (16) and Sarah (17) untouched.

---

## Done

### Check 2 — W-0014's Joint Owner select: NON-REPRODUCTION, with evidence

W-0014's repro claimed the **create** modal never reveals the Joint Owner select, so
joint ownership was reachable only through the edit form. **It does not reproduce.**

`/net-worth/investments` → Add Account → Ownership Type = **Joint Owner**:

```
#joint_owner_id           present, SELECT
options                   ["", "30 — Arjun Raman (Spouse - Linked Account)"]
display                   inline-block
visibility                visible          opacity 1
pointer-events            auto             disabled false
size                      624 × 39.5       overlay none
hitTestSelf               true             elementFromPoint → joint_owner_id
real interaction          selectOption('30') → OK, value "30"
```

**The nuance that probably explains the original report.** On first measurement the
select read `inViewport: false`, `hitTestSelf: false`, `elementFromPoint → NONE` — it
renders **below the fold** in a 1200×673 viewport. Only after
`scrollIntoViewIfNeeded()` did it become hit-testable. A tester reading a snapshot
without scrolling the modal would reasonably conclude the control was absent. I nearly
recorded "not hit-testable" on that first reading; scrolling and re-measuring is what
made the answer honest.

**Recorded as an explicit non-reproduction so it does not become folklore.**

Also confirmed in passing: there is **no `ownership_percentage` input on the create
modal**, exactly as `SharedOwnership`'s docblock assumes ("No form in the app exposes a
share input for joint ownership"). The 50% genuinely comes from the server default —
Batch A's design works the way it says it does.

### W-0052 — creating any investment account returns 500 (critical, regression)

Found on the action immediately after the W-0014 check: completing the save.

```
POST /api/investment/accounts → 500
SQLSTATE[23000]: Integrity constraint violation: 1048
Column 'advisor_fee_percent' cannot be null
```

| Layer | State |
|---|---|
| Column | `advisor_fee_percent decimal(5,4)` **NOT NULL**, default `'0.0000'` |
| Validation | `StoreInvestmentAccountRequest.php:59` — `['nullable','numeric','min:0','max:10']`, **added by the W-0008 fix** (confirmed in `git diff`) |
| Frontend | `AccountForm.vue:971` — `submitData.advisor_fee_percent = null` when the additional-information panel is collapsed |

Before W-0008 the field was not in the rules, so `validated()` stripped it, the INSERT
omitted the column, and the database default applied. Adding a `nullable` rule is
exactly what lets the frontend's explicit `null` reach a NOT NULL column. The modal
stays open and the user sees nothing.

**Blocking:** four of this persona's records are investment accounts, and the joint
share cannot be verified on a new record if no record can be created.

### The free-tier capability matrix, read live and folded into the playbook

Playbook §0.2 said "roughly a quarter of the persona is unreachable at free tier". That
is now an exact table read from `TierConfigurationStore`, covering all 30 capability
keys for both tiers.

**Two semantics worth recording, because both are easy to misread:**

1. `TeaserGate::allows()` returns true only for `full`, so it returns **false** for a
   `limited` capability even when the user is nowhere near the cap. `allows() === false`
   means "not unlimited", **not** "cannot add".
2. `TierConfigurationStore::capabilityFor()` returns `'none'` for any key **absent from
   the matrix** — a deliberately bogus key returns `none` too. So a `none` reading is
   only meaningful for keys that are actually in the matrix.

Both mattered here: my first probe used the keys `savings` and `retirement`, got `none`
for premium, and briefly looked like a defect. The real keys are `savings_account` and
`pension_account`, and premium has both at `full`. **Checked before raising; nothing
was raised.**

---

## Not done, and why

- **Check 3, cap-lift without re-login: BLOCKED on provisioning.** Priya is free tier
  with her session open — the ideal subject — but the test needs premium granted
  **without touching the browser**, which is the coordinator's to do. Baseline captured
  and ready.
- **Batch B's leads: BLOCKED on access.** All of them need a premium account with a
  linked spouse. Priya is free (`estate: teaser`, so no will builder). David and Sarah
  are premium and linked but are the batches' reproduction data, and the key lead —
  the Review step rendering the executor-is-testator error with **Complete & Finalise
  disabled**, the screenshot the coordinator most wants — requires *constructing* a
  will, which would modify them. Adam and Beth (18/19) are Batch B's own pair and not
  mine to drive.
- **Could not prove `ownership_percentage = 50` persists on a newly created joint
  investment account** — W-0052 prevents creating one. That is the one part of Batch A's
  claim still unverified end to end by me.

---

## Assumptions

1. Using throwaway Priya rather than David/Sarah for write-heavy checks is correct,
   since David and Sarah are the batches' reproduction data.
2. W-0052 is a regression **of the W-0008 fix specifically**, on the evidence of the
   `git diff` adding exactly that rule. I have not bisected to prove no other change
   contributes.

---

## Needs

1. **Premium on `users.id 20`, granted without touching the browser**, then tell me — I
   will attempt a capped action with no reload and report whether the cap lifts
   in-session or needs a re-login.
2. **A decision on Batch B's leads:** either premium on Priya (which also unblocks the
   will-builder leads on a clean account), or explicit clearance to drive Adam/Beth.
   Premium on Priya is the better option — it serves both outstanding checks at once.
3. **W-0052 assigned urgently.** It blocks the investments module for the re-run and for
   any user on an environment where it has landed.

---

## Noticed

- The free-tier matrix confirms W-0011 exactly: `expenditure_detailed: none` on free.
- `investments_exotic: none` on free means the persona's **Venture Capital Trust**
  holding is not merely capped but unavailable — worth knowing when reading any
  free-tier investment result.
- `joint_household_view: none` on free — the entire purpose of this persona run is a
  premium-only capability. Worth stating plainly in any release conversation about what
  free tier is for.

---

## Context position

Roughly **545k**. Inside the Rule 22 buffer.
