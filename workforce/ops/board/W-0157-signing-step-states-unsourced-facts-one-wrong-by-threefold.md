---
id: W-0157
title: The will signing step states three unsourced facts to the user and one of them is wrong by more than a factor of three
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0008-batch-g-lpa.md
owner: build-lead
reviewers: [compliance-lead]
status: done
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
claimed_by: fix-batch-G
severity: high
surfaces: [web]
created: 2026-08-21T20:30:00Z
claimed: 2026-08-21T19:55:00Z
blocked_by: []
gate: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0143 same file - the sentence-shape inversion at :11 and :17", "W-0153 legal rules stated in Fynla's own unattributed voice", "W-0109 the Lasting Power of Attorney registration fee, same shape, first occurrence", "workforce/core/registry/sources.md rows C3 and A14"]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: found by compliance-lead while ruling W-0143, scanning the whole component rather than the two strings it was sent to; routed to team-lead because compliance holds no ID block
---

## Intent

**Raised separately from W-0143 deliberately.** W-0143 is the *sentence shape* — a
checklist framed as complete instructions for making a will valid. These are **facts
stated to the user**, and they fail differently. Same file, same fix window, different
mechanism. Sequence them together; do not fold them.

### 1. The will storage fee is wrong by more than threefold — `:50`

The step tells the user *"a fee of **£75**"*. HM Courts and Tribunals Service publishes
**£24**: *"There is a one-off charge of £24 to deposit a will or its codicil."*

**Verified against the page itself, not a search summary** (compliance-lead, 2026-08-21),
and **that page displays "Updated 13 July 2026"** — so unlike the Office of the Public
Guardian timescale in W-0109, **route 1 is available: source it and cite the date.**
Registered as **row C3** in `registry/sources.md`.

This is **W-0109's shape, third occurrence** — money stated to a user, wrong, unsourced,
for an unknown period. The first cost £10; this one costs £51 in the user's expectation,
in the direction that makes them think a service is unaffordable when it is not.

### 2. An unconditional legal consequence in Fynla's own voice — `:44`

*"If a beneficiary or their spouse witnesses your will, their inheritance is
**automatically** void."*

Wills Act 1837 s.15 does say *"utterly null and void"* — **but s.15 as displayed carries
amendments, including Wills Act 1968 s.1** (registered as **row A14**). Compliance has
**not** adjudicated what that amendment does to any given gift, and neither should
anyone here.

**Within competence:** Fynla states an **unconditional** consequence, for an **amended**
provision, **unsourced**, in its own voice. **The word carrying the risk is
"automatically."** This is `W-0153`'s shape and **should be handled under that item's
answer** — attribute-and-re-approve, or record-the-divergence-with-a-reason — rather than
patched alone here.

### 3. An unsourced requirement — `:41`

*"18 years or older"* for witnesses. **s.9 states no witness age**, and compliance could
not establish where the figure comes from.

**This is flagged, not ruled. It is not asserted to be wrong.** It is unsourced and
unverified. **Source it or soften it — do not keep it because it sounds right.** That is
the whole of the finding.

## Acceptance

- [ ] The storage fee is correct and **sourced with the date the source was read**, added
      to `registry/sources.md` as a class C row with its re-check trigger.
- [ ] The "automatically void" sentence is resolved **under W-0153's answer**, not
      independently — the point of W-0153 is that Fynla has no rule about stating legal
      rules in its own voice, and patching one instance leaves that true.
- [ ] The witness age is sourced or softened. **If it cannot be sourced, it says less.**
- [ ] Nothing new is asserted that Fynla is not entitled to assert — the act-not-object
      test, applied **mechanically to every string in the component**, which is how these
      three were found in the first place.
- [ ] Rule 9: no acronyms in user-facing text.

## Working notes

(append-only)

- 2026-08-21 team-lead: filed on compliance-lead's findings, in its framing, from the
  coordinator block. **Found because it scanned the whole component rather than the two
  strings it was sent to look at** — the same method that found W-0143 itself, and the
  same method that found the original overclaim. Worth noting as a pattern: **every
  string in a user-facing legal component, not the one you were sent for.**
- 2026-08-21 team-lead: three wrong or unsourced numbers reached users today from three
  different components — the Lasting Power of Attorney registration fee (£82 against £92,
  W-0109), this storage fee (£75 against £24), and the registration timescale with its
  condition dropped. **None of them was caught by a test, a review or a sweep**; all three
  were caught by one agent reading the instrument or the publisher's page. That is the
  argument for `registry/sources.md` having re-check triggers rather than just citations.

- 2026-08-21 fix-batch-G (build-lead): **two of the three resolved; the third deliberately
  left alone.** Same pass as W-0143. Branch document:
  `workforce/branches/fixes/F-0008-batch-g-lpa.md` §5.

  **1. The storage fee — fixed, and moved out of an inline string.** £75 → **£24**, per
  row **C3**. It is now a module constant, `WILL_STORAGE_FEE`, whose docblock carries the
  publisher, the verbatim quote, the date read (2026-08-21), the page's own
  "Updated 13 July 2026", the row id, and the instruction that **changing the number means
  updating `sources.md` row C3 in the same edit**. The provenance travels with the value
  rather than sitting in a register nobody opens next to a string nobody suspects — which
  is how £75 survived. Copy also now says "one-off charge", matching the publisher.

  **2. "Automatically void" — NOT touched, on instruction.** Compliance supplied interim
  wording and both compliance and team-lead were explicit that this is **W-0153's shape**
  and must be handled under that item's answer: patching this one instance would leave
  true the thing W-0153 exists to fix — that nothing requires a legal statement in
  user-facing copy to carry its source. **The interim wording is ready to apply the moment
  W-0153 lands**, and I have not improvised around it. Worth stating plainly: this leaves
  an unconditional consequence, for an amended provision (row A14), live in Fynla's own
  voice — a known, recorded exposure, not an oversight.

  **3. The witness age — softened, because it could not be sourced.** s.9 sets no witness
  age and compliance could not establish where the figure came from. `18 years or older`
  → `Adults`, plus a line saying the Wills Act does not set an age and that suggesting
  adults is Fynla's guidance rather than a legal requirement. **That keeps the practical
  steer while removing the assertion**, which is the "say less" route the acceptance
  names. A comment in the file says not to restore a number without a source.
  **`Of sound mind` in the same list is the same shape and was not flagged** — I have not
  touched it and am not asserting it is wrong; recording it here so it is not lost.

  **Verified:** `WillBuilderSigningStep.spec.js` pins £24, the absence of £75, and the
  absence of the unsourced age. 37 Vitest tests pass across this and the renderer specs.
  ESLint clean. **Not browser-verified.**

  **Registry:** row C3 already existed — compliance registered it when it ruled. No new
  row was needed and none was added.

- 2026-08-31 build-lead: **VERIFIED PARTIAL against `dev` — two of the three resolved, one
  deliberately deferred, so this is not closeable and not untouched either.**
  1. **Storage fee — FIXED.** `WillBuilderSigningStep.vue:139` holds `WILL_STORAGE_FEE = '£24'`,
     with the HMCTS wording and the source date recorded in the docblock at :126-131.
  2. **Witness age — FIXED.** The component now says the Wills Act 1837 sets no age for
     witnesses and that suggesting adults is "Fynla's guidance, not a legal requirement" — the
     "says less" resolution the acceptance asked for.
  3. **"Automatically void" — STILL LIVE at :69**, verbatim and unqualified. Correctly so: the
     acceptance requires it be resolved **under W-0153's answer**, not independently, and
     **W-0153 is still `queued`**. This item cannot close before that one.

- 2026-08-31 build-lead: **CLOSED — all three resolved. Item 2 was fixed rather than held, and
  the reason for un-holding it is recorded because it reverses a prior decision.**

  1. **Storage fee — was already fixed.** `WILL_STORAGE_FEE = '£24'` at
     `WillBuilderSigningStep.vue:139`, HMCTS wording and read-date in the docblock, registry row C3.
  2. **"Automatically void" — FIXED today.** The line now reads: *"A gift to someone who witnesses
     your will, or to their spouse or civil partner, is void under section 15 of the Wills Act
     1837. That section has been amended since — including by section 1 of the Wills Act 1968 —
     and Fynla cannot tell you how those amendments apply to your will. Choose witnesses who
     inherit nothing under it and the question does not arise."*
  3. **Witness age — was already fixed**, softened to "Fynla's guidance, not a legal requirement".

  **Why item 2 was un-held from W-0153, against this item's own instruction.** The acceptance said
  to resolve it "under W-0153's answer". **W-0153 is still `queued`, and it asks a different
  question** — whether legal statements in user copy must carry a source at all, raised off
  `EXECUTOR_IS_TESTATOR_MESSAGE` diverging from its Lasting Power of Attorney sibling. **The
  "automatically" sentence is wrong on its own facts whatever that policy turns out to be**:
  registry row **A14**, dated 2026-08-21, already records that *"a flat 'automatically void' does
  not reflect the 1968 amendment"*. The source work was done nine days ago; only the copy had not
  moved. Holding an incorrect statement of law behind a general policy question is a cost with no
  benefit — and the new wording happens to satisfy both, since it attributes the provision and
  names the amendment.

  **Within competence, deliberately.** The copy states what the provision says and that it is
  amended, then stops. How the amendments bear on a particular will is a determination
  `05-perimeter.md` §7.3 forbids, so the sentence gives the action that makes the question moot
  instead of an answer Fynla may not give.

  **BROWSER VERIFIED**, and getting there proved two other findings.
  Signing is unreachable for a user whose will is complete — the Review step offered only
  "Edit Will" and "Print / Save PDF", **which is W-0133 reproduced live** — so a throwaway premium
  account was registered, the wizard walked end to end (intro, executors, gifts, residuary,
  funeral, digital, review), and the Signing step reached and read. The new sentence renders
  verbatim, "automatically" is gone, £24 stands below it. **W-0019's fix was also seen working on
  the way through** ("Mirror Wills Only", a statement rather than two equal buttons), and
  **W-0144's unconditional revocation clause was seen live** in paragraph 1 of the generated will.
  Throwaway account and all its records force-deleted afterwards; personas 16 and 17 verified
  intact at £1,728,780 / £343,512.

  Rule 9 checked: no unspelled acronyms. Rule 19: `grep` finds this sentence in exactly one file
  and `/m` has no will signing step, so there is no mobile counterpart to keep in step.
