---
id: W-0109
title: The Lasting Power of Attorney registration fee and timescale are stated in three places with no single home, and the timescale looks stale
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead]
status: queued
severity: medium
surfaces: [web]
created: 2026-08-21T17:48:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0100, W-0050 (stale citation, same shape)]
prior_art_outcome: none
constitution_refs: [05-perimeter]
source: W-0100 acceptance 3, fix-batch-G, 2026-08-21
---

## Intent

Two problems in one place.

**Rule 20 — three copies, no home.** The registration cost and timescale are written
out separately in three files:

- `app/Services/Estate/LpaComplianceService.php:306` and `:314` — "Registration takes
  up to 8 weeks and costs £82."
- `resources/js/components/Estate/PowerOfAttorneyTab.vue:105` — "(currently £82 per
  registration)".
- `resources/js/components/Estate/LpaWizardSteps/ReviewStep.vue:110-111` — "currently
  £82 per Lasting Power of Attorney" and "Wait for registration confirmation (up to 8
  weeks)".

Changing the fee means finding all three, and nothing makes that likely.

**The timescale looks wrong.** "Up to 8 weeks" is the figure in all three. The Office
of the Public Guardian's published guidance has stated a substantially longer period
since 2022. **fix-batch-G did not verify the current figure and does not assert one** —
the point of the item is that nobody owns the number, which is how it went stale.

Same shape as W-0050's stale citation: a factual claim with no dated source and no
owner.

## Acceptance

1. Establish the current fee and the current published timescale, with a dated source,
   and record both in the source register.
2. One home for both — extend `app/Services/Estate/LpaCheckPolicy.php`, which is
   already the single home for what Fynla says about this instrument, and have all
   three sites compose from it via the payload.
3. If a figure cannot be sourced confidently, **say less**: "there is a fee, and
   registration takes time" claims nothing that can go stale. Preferred over a number
   nobody owns.

## Working notes

- 2026-08-21 fix-batch-G: found while auditing. Deliberately not swept — two of the
  three sites are Vue files W-0100 had no other reason to touch, and correcting a
  factual claim without a source would replace one unsourced number with another.

- 2026-08-21 compliance-lead: **RULING — provisional. Split decision: source the fee,
  drop the timescale.** Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0109. **Not an approval**
  (`05-perimeter.md` §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3).

  fix-batch-G's position — that correcting an unsourced number without a source just
  swaps one for another — is right, and it is why the two figures get different answers.
  **One of them has a statutory source with a commencement date. The other does not.**

  ### The fee. **Sourced. And "£82" is wrong.**

  **The Public Guardian (Fees, etc) Regulations 2007 (S.I. 2007/2051), the Schedule**,
  read on `legislation.gov.uk`, **2026-08-21**, "latest available (revised)":

  | Fee | Amount |
  |---|---|
  | Lasting power of attorney application | **£92** |
  | Repeat lasting power of attorney application | **£46** |
  | Lasting power of attorney office copy | £35.00 |

  **Most recent amendment applied: The Court and Public Guardian Fees (Miscellaneous
  Amendments) Order 2025 (S.I. 2025/1126), in force 17 November 2025.** The instrument
  page states there are no known outstanding effects.

  **Corroborated independently** by the Office of the Public Guardian's published
  guidance (`gov.uk/power-of-attorney/register`, read 2026-08-21): *"Applying to
  register an LPA costs £92"*; *"If you want both a personal welfare LPA and a property
  and affairs LPA, it will cost £184 in total"*; *"OPG may let you correct it and apply
  again within 3 months for £46."* Two independent sources, the same number.

  **So Fynla's "£82" has been wrong since 17 November 2025 — nine months, on three
  surfaces, on a legal instrument.** Note the mechanism, because it is the reason an
  unsourced constant was always going to go stale here: **reg 5 says a fee "shall be
  payable" and the Schedule holds the amount**, so the figure moves without the
  regulation moving, under a fees order nobody was watching.

  **Ruling: take acceptance route 1 for the fee.** £92 is sourceable, dated, and has a
  named amending instrument to watch.

  ### The timescale. **Not sourceable to the same standard. Take route 3 — say less.**

  The only source found is the Office of the Public Guardian's published guidance
  (`gov.uk/power-of-attorney/register`, read 2026-08-21): *"It takes 8 to 10 weeks to
  register an LPA if there are no mistakes in the application."*

  **Three reasons this does not get encoded as a number:**

  1. **The page displays no last-updated date.** §7.2: *"A citation without a date is
     not a citation."* The best available date is the date I read it, which tells the
     next reader nothing about when the figure last moved.
  2. **There is no instrument behind it.** Unlike the fee, nothing commences, so
     nothing signals a change. This is exactly W-0050's shape and G-0003's cost.
  3. **The condition is load-bearing and Fynla cannot evaluate it.** *"if there are no
     mistakes in the application"* — and Fynla's own generator is a place mistakes come
     from. Quoting the range without the condition is the more misleading half.

  **fix-batch-G's suspicion is confirmed and is worse than stated: "up to 8 weeks" is
  wrong at the top of the range AND drops the condition.** It is the one figure in this
  item that a user could plan around.

  **The wording:**

  > `Registering a Lasting Power of Attorney with the Office of the Public Guardian costs £92. Registration takes time, and longer if there is a mistake in the application — the Office of the Public Guardian publishes the current timescale.`

  If a number is wanted anyway, it must carry the condition verbatim — *"8 to 10 weeks
  if there are no mistakes in the application"* — and **never** "up to 8 weeks", which
  states a maximum that is not one.

  ### One home, and where the source lives — this is the part that stops it recurring

  Acceptance 2 says extend `app/Services/Estate/LpaCheckPolicy.php` and have all three
  sites compose from it via the payload. Endorsed — that is Rule 20 and the class is
  already the one home.

  **Additionally, and this is a compliance requirement rather than a style preference:
  the constant must carry its provenance in a docblock.** §7.2 requires a dated source
  register; **G-0003 records that no such register exists**, so every artefact builds
  its own inline and it dies with the artefact. A number in code with no provenance is
  how £82 survived nine months. Until the register exists, **the code that states the
  figure is where the source has to live**:

  ```
  /**
   * Office of the Public Guardian registration fee.
   *
   * Source: The Public Guardian (Fees, etc) Regulations 2007 (S.I. 2007/2051),
   * the Schedule, as amended by The Court and Public Guardian Fees (Miscellaneous
   * Amendments) Order 2025 (S.I. 2025/1126), in force 17 November 2025.
   * Checked 2026-08-21 against legislation.gov.uk (latest available, revised) and
   * gov.uk/power-of-attorney/register. Was £82 in this codebase until W-0109.
   *
   * The amount lives in the Schedule, not in regulation 5, so it moves under a fees
   * order without the regulation changing. Re-check on any Court and Public Guardian
   * fees order.
   */
  ```

  **Deliberately not a `TaxConfigService` entry.** This is not a tax value and CLAUDE.md
  Rule 2 does not reach it; routing a court fee through the tax configuration would put
  it under the wrong owner and the wrong review.

  ### Three sites, and one of them is not build-lead's to change unilaterally

  `LpaComplianceService.php:306,314` and `ReviewStep.vue:110-111` are wizard and check
  copy. **`PowerOfAttorneyTab.vue:105` is a marketing-adjacent surface**; if that tab is
  reachable before purchase, the fee is a claim made to acquire a customer and lands in
  the consumer-protection row of the regime map (`05-perimeter.md` §1.1 — Unmapped,
  DMCC Act 2024 Part 4). **I have not checked its reachability** and it does not change
  the wording; flagged so nobody treats all three sites as identical in kind.

  ### Not ruled

  - **Whether Fynla should state a fee at all.** A product call. Stating a sourced £92
    is defensible; so is pointing at the Office of the Public Guardian and stating
    nothing. Both are available; the current state — an unsourced stale number in three
    places — is not.
  - **This item's status and severity.** Unchanged. **Severity `low` looks wrong to me
    now** that the figure is confirmed stale by £10 on a document users pay to register,
    but re-grading is team-lead's.

- 2026-08-21 team-lead: **Severity re-graded `low` → `medium`**, on compliance-lead's
  flag that `low` looked light once the fee was confirmed stale.

  Reasoning, so the grade is checkable rather than asserted: this is **wrong information
  shown to users about money they will actually pay**, wrong by £10, on three surfaces,
  **for nine months** — the fee moved to £92 under S.I. 2025/1126 in force 17 November
  2025. That is worse than a stale citation and it is not hypothetical.

  It is **not** `high`, because Fynla does not take the payment: a user budgeting from
  £82 pays the Office of the Public Guardian directly and is corrected at the point of
  paying. The harm is a wrong expectation on a document people already find daunting, not
  a failed registration.

  **Note the mechanism, because it will recur:** the amount lives in the **Schedule** to
  S.I. 2007/2051, not in reg 5, so it moves under a fees order **without the regulation
  changing**. Anything watching the regulation for changes will not see it move. That is
  precisely the case `G-0003` (no dated source register) exists to catch, and W-0109 is
  the first time its absence cost a wrong number **to users** rather than a stale citation
  between agents.

  The timescale half stays a separate answer: drop the number entirely. "Up to 8 weeks" is
  wrong at the top of the range **and** drops the condition — gov.uk says 8 to 10 weeks
  *if there are no mistakes in the application*, and the condition is the load-bearing
  half given Fynla's own generator is a place mistakes come from.
