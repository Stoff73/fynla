# Cycle 4 certification — quality-lead, 2026-08-23

**Gate:** 149 board items at `status: handoff`, uncertified, already deployed to dev
on CSJ's instruction. This file is the merge gate's record. Appended as each batch
finishes — never held in context and written at the end.

**Repo:** `/Users/CSJ/Desktop/fynla`, branch `estate-copy-and-m-handoff`.
**dev deployed to csjones.co/fynla at `5c556e252`.**

**The branch moved under me while I worked, and the record should say so.** It carried two
commits beyond dev when I was dispatched (`88494e0fd`, `8f09eaddc`); by the time I
finished it carried six — `809743261`, `38452d8f7`, `d772a41bb`, `8de2a6676` landed
during the pass. Verdicts below were reached against the tree as it stood when each item
was judged, and I re-checked W-0325 and W-0327 against HEAD (see the addendum).

**Verdicts:** CERTIFIED · REJECTED (named unmet criterion) · CANNOT CERTIFY (what is missing).

---

## FINDING 0 — STRUCTURAL, applies to every item judged

**Not one evidence pack exists.** `08-process.md` §2 requires the pack at
`workforce/branches/<type>/<slug>/evidence/`, permalinked from the PR before merge.

```
$ find workforce/branches -type d -name evidence | wc -l
0
```

There are 34 branch documents under `workforce/branches/fixes/` and **zero** evidence
directories. What evidence exists lives inline in each board item's *Working notes* —
prose written by the agent that wrote the code.

Two consequences, and they are different in kind:

1. **Location.** No pack is where the constitution says a pack must be. This is
   recoverable — the substance is judged below on where it actually sits.
2. **Authorship.** `08-process.md` §2.4: *"The agent that wrote the code does not
   produce the evidence pack."* Every inline evidence note in these 149 items was
   written by `build-lead` — **the agent that wrote the code**. By the constitution's
   own definition that is self-certification, and *"a gate that permits it is
   decoration."*

I have therefore treated every build-lead claim as a **hypothesis to be checked against
the source tree**, not as evidence. Where I could falsify or confirm a claim by reading
the code, the test, or the schema, I did, and I say so per item. Where the only evidence
is the author's assertion that they saw something in a browser, that is recorded as
unverified — it is not a lie, but it is not the pack either.

---

## FINDING 1 — the format split, and what an acceptance list is worth on this board

| Item shape | Count | Consequence |
|---|---|---|
| Has `## Acceptance` | 125 | Certifiable in principle |
| **No `## Acceptance` section at all** | **26** | **Nothing to certify against** |

The 26 with no acceptance criteria:
W-0040 · W-0132 · W-0134 · W-0135 · W-0136 · W-0137* · W-0138 · W-0140 · W-0172 ·
W-0173 · W-0174 · W-0175 · W-0176 · W-0177 · W-0186 · W-0187 · W-0188* · W-0190 ·
W-0203 · W-0206 · W-0207 · W-0210 · W-0217* · W-0228

(*W-0137, W-0188, W-0217 carry acceptance criteria under `### Acceptance` — a
sub-heading, not `##` — so the count of genuinely criterion-less items is lower than
the grep suggests. Corrected per item below.)

A second, sharper split among the 125:

| Acceptance state | Count |
|---|---|
| Checkboxes present, **not one ticked** | 30 |
| Checkboxes present, partially ticked | 19 |
| Prose criteria, no checkboxes | 76 |

**30 items sit at `handoff` with an acceptance list on which no box has ever been
ticked.** That is not evidence of failure — several were demonstrably fixed and the box
was simply never maintained (W-0006 is one: the fix is real and in the tree, and all six
boxes are blank). It is evidence that **the checkbox carries no information on this
board in either direction**, which retires it as a certification signal. Confirming the
brief's warning about W-0263, from the opposite side: an unticked box means as little as
a ticked one.

---

## PRIORITY 1 — the items with no browser-evidence marker

The queue lists 22; it names 21. W-0263 has since gained browser evidence, leaving 20.
Verdicts for those 20 are recorded below, after the code-quality half.

---

## THE CODE-QUALITY HALF — run by me, on the branch, not by the authors

This is the one part of the evidence pack that did not exist and now does. I ran it;
`build-lead` wrote the code. That separation is what makes it evidence
(`08-process.md` §2.4).

### `./vendor/bin/pest` — full suite, branch `estate-copy-and-m-handoff`

```
Tests:    30 skipped, 7886 passed (127903 assertions)
Duration: 1966.39s
EXIT_CODE=0
```

Zero failures. Zero errors. Counted independently: `grep -cE '^\s+(⨯|FAIL)'` → **0**.

Run as a single process, nothing else touching `laravel_testing`, per the concurrency
trap that produced the phantom "232 failures" and "61 failures" on 2026-08-22/23.

**The run window, stated precisely, because the branch moved during it.** The suite
started ~21:47 and finished 22:20. **Three commits landed inside that window** —
`38452d8f7` (21:55), `d772a41bb` (22:01), `8de2a6676` (22:04). A suite that collects and
runs against a moving tree is not automatically trustworthy, so I checked what each
touched rather than assuming:

| Commit | Files |
|---|---|
| `38452d8f7` | one board `.md` |
| `d772a41bb` | `resources/js/views/Savings/SavingsAccountDetail.vue`, one board `.md`, this file |
| `8de2a6676` | one board `.md` |

**No PHP, no `tests/`, nothing Pest collects.** The one code file is Vue, which belongs to
vitest, not to this run. So the result stands for the tree Pest actually executed —
`8f09eaddc` (21:41) and everything before it. **It does not cover
`SavingsAccountDetail.vue`**, and no PHP-side claim depends on it.

**7,886 against the 7,878 recorded at `19bd1c83f`** — consistent with the two estate
commits on this branch adding eight tests. The suite is genuinely green for the first
time since the `spouseRow()` collision fatal, and it is green *including* the two
commits that are not yet on dev.

The 30 skips are the known-and-accepted set: `CassetteModelProvenanceTest` (deferred
by decision — do not surface), `EvalHttpDriverTest` (live integration, needs a real
provider key).

### `./vendor/bin/pint --test`

Reports `"result":"fail"`, but **not one flagged file is application code.** All 61 are
`public/pages/**` server-rendered marketing pages, plus one:

**`workforce/ops/ui/index.php` — added this cycle at `5c556e252`, unformatted.**
The only genuinely new Pint violation this board produced. Cosmetic, in an ops tool,
not shipped to users. Recorded, not blocking.

`app/`, `tests/`, `database/`, `routes/` are all Pint-clean.

### What is still missing from the code-quality half, for every item

Never run, by anyone, on any of the 149: `security-reviewer` (and W-0344/W-0347/W-0348/
W-0349 are *auth and cross-account disclosure* items, exactly its trigger),
`tax-compliance-reviewer` on the tax/projection items, `design-lint.sh` on the UI diffs,
`tech-debt-session`, `tax-hardcode-check`, `m-parity-check`. Six of the seven rows in
`08-process.md` §2.1.

---

## THE STANDARD I AM APPLYING — stated once, so every verdict below reads the same way

- **CERTIFIED** — the acceptance is met in substance; I opened the named files and the
  change is in the tree; the tests genuinely guard it (they would fail if the mechanism
  did nothing); and evidence exists for the surfaces the change actually reaches.
- **REJECTED** — a specific criterion is substantively unmet, or the defect the item
  exists to fix still reproduces. The unmet criterion is named.
- **CANNOT CERTIFY** — the work looks right but the required evidence is absent or
  unobtainable, or a decision is outstanding. **This blocks the merge; it is not a
  finding against the work.** It is the honest answer where the alternative is a
  fabricated "verified".

**Three gates apply board-wide before any individual verdict.**

### Gate A — iOS. 97 of 149 items claim `surfaces: [..., ios]`.

`08-process.md` §3 and `CLAUDE.md`: Playwright cannot drive native SwiftUI, so a change
touching the native client needs CSJ's device verification. The cycle-4 tester brief
states it plainly: **"iOS is untested throughout. Not built, not launched, not claimed."**

Only **5** native files were actually changed this cycle (`AppRootView.swift`,
`WebHandoffClient.swift`, `EstateView.swift`, `NavigationDestinationFactory.swift`,
`WebHandoffClientTests.swift`), so for most of the 97 `ios` is a claim of *reach* through
a shared endpoint, not a native code change. I have therefore **not** blocked all 97 on
Gate A. I apply it where the item changed native code, or where a native surface holds a
**parallel mechanism** that the fix did not reach (Rule 20) — which is a different and
more serious thing than an unverified surface.

### Gate B — browser evidence. Required by `08-process.md` §2.2 and by CLAUDE.md's
browser-testing rules for every user-facing journey. Absence is CANNOT CERTIFY.

### Gate C — authorship. Every browser claim below was written by the agent that wrote
the code. Where I say "browser evidence present" I mean *the item records a specific,
falsifiable interaction* — a DB row before and after, an HTTP status, a quoted screen.
I did not re-drive any journey myself; the single Playwright tab was not mine to take and
re-driving 149 journeys is not a session's work. **That is the pack's remaining hole and
I am naming it rather than papering it.**

---

## BATCH A — W-0006 … W-0036 (31 items)

Fix-presence verified by me against the source tree for all 31. **30 of 31 are present
as claimed.** The exception is the most important verdict in this batch.

### REJECTED (2)

**W-0012 — mortgage term hardcoded and Rate Fix End Date dropped. REJECTED.**
**Unmet criterion: the wizard payload still drops the field in the browser.**
The backend half is real — `MortgageNormaliser::reconcileTerm():124` derives the term,
`MortgageService.php:66` maps `rate_fix_end_date`, `StorePropertyRequest.php:100-102`
adds the rules. **The frontend half is absent.**
`resources/js/components/NetWorth/PropertyList.vue:267-280` still hand-copies a fixed
subset of mortgage fields, and `mortgage_rate_fix_end_date` is not among them.
`grep mortgage_rate_fix_end_date resources/` returns **zero hits**;
`git log -- PropertyList.vue` shows the file untouched since `911f39a2e` (2026-08-04),
before this work began.

**Why the test did not catch it, which is the part worth keeping.**
`PropertyHttpIntegrationTest.php:130-177` asserts `rate_fix_end_date → '2027-04-01'` and
passes — because it **POSTs the key directly to the API**. The test and the browser take
different doors. This is the Fixture variant in `tests/CLAUDE.md`: the test constructs
the input the browser never sends, so it proves the receiver works while the sender is
still broken. A green test, a real fix, and the user's defect entirely intact.

**W-0008 — adviser fee cannot be entered. REJECTED.**
**Unmet criterion: acceptance bullet 4.** The input now exists and persists
(`StandardInvestmentFields.vue:252`, rules at both requests, HTTP test genuinely guards).
But the item's title is that the fee is *"displayed and charged in projections, but always
£0"*, and the author states plainly: **"I did not verify the fee-drag / net-of-fee
projection figures move."** Entering a number without showing it reaches the figure it
was entered for leaves the headline defect unproven. Separately, `advisor_fee_percent`
has **0 hits in `resources/mobile/` and 0 in `ios-native/`** against a claimed
`[web, m, ios]`.

### CANNOT CERTIFY (17)

| Item | What is missing |
|---|---|
| **W-0006** | Fix verified present and the test genuinely guards. Acceptance 4 and 6 both require the browser; neither was done. The only DB read-back in the item is the persona-tester's, on a **different write path** (onboarding), which does not exercise this fix. |
| **W-0007** | Web browser evidence is good (cold-load panel quoted). But `ios-native/` holds **5 files referencing `isa_allowance` and none were changed** — a Rule 20 parallel-mechanism question the item did not answer. Author declares "iOS not checked". |
| **W-0010** | Strong tests including an over-correction guard. Zero browser evidence on a UI dead-end — the defect *is* a rendering absence, so a component test is the weakest possible proof. |
| **W-0011** | Both halves present, tests guard. No browser, no DB read-back, on a **tier-gated** journey where the gate is the thing being changed. |
| **W-0014** | **The implemented rule is not this item's acceptance.** Acceptance bullet 1 says a stated 100 is rewritten to 50; the code now **refuses it with a 422** (later W-0040/CSJ ruling). The item was never restated. Also: assertion (a) would have passed pre-fix — the old `isset()` default already produced 50 when the key was absent, so it is a **Collision**; and `AccountForm.vue:1124`'s `delete submitData.ownership_percentage` — the actual frontend fix — has no test at all. |
| **W-0015** | Excellent evidence otherwise (the `60 → 40` assertion genuinely guards; both accounts, both screens quoted). **Acceptance bullet 3 is unmet as written and the author flagged it for the Chief of Staff** — a deliberate 100/0 split is not preserved. That is an open decision, not a defect. |
| **W-0017** | Fix present across form, tool schema v4 and allowlist. No browser evidence. One named test is a probable **Collision** — `HouseholdPlanningService:791`'s `?? 50` returns the same number as a *recorded* 50, so a spouse-pension test using 50 proves nothing; the persona-shaped case does guard. |
| **W-0019** | Browser evidence is strong (live 422 for `simple`, 201 for `mirror`, same session). Blocked on **two outstanding CSJ decisions**: acceptance 6 (existing one-sided wills) is "STILL CSJ'S CALL", and compliance-lead leaves the Consumer Duty trade open. Build-lead states "Rule 14's loop is NOT closed by me". |
| **W-0020** | Fix present, tests genuinely guard (the config test cannot pass against a hardcoded `?? 0.36`), browser end-to-end with `getCharitableBequestTotal() = 10000`. **Blocked on eight unactioned `tax-compliance-reviewer` findings, two of them High (F3, F4)** — on the mechanism that decides the reduced IHT rate. Tax compliance is a hard block. |
| **W-0024** | Browser evidence good. **Open production gate for CSJ** — the defect is on `origin/main` (`9cfeadb46`) and the prod count of affected mirror wills was never run. The author's own 2026-08-23 addendum states the fix **had a hole** (single-spelling name match), now W-0396, with residue cleanup as W-0395. |
| **W-0025** | Author states verbatim **"I COULD NOT CLICK THIS THROUGH THE FORM"** — the shared browser was held by another agent. HTTP-layer proof with a before/after table is real but is not the journey. One genuine orphan row (`mortgages.id = 7`) reported and deliberately unrepaired. |
| **W-0030** | Backend + migration present, tests guard. **csjones and production were never surveyed for affected rows** — the author says so and says someone with access should check the count before the migration runs. A data-rescaling migration whose blast radius is unmeasured. |
| **W-0031** | Fix present, tests read `INFORMATION_SCHEMA`. Pre-fix 500s were proven live; **no post-fix browser interaction** was recorded. Author also carried the live Rule 9 acronym "Secondary (GCSE/O-Levels)" to a **third** surface and raised it undecided. |
| **W-0032** | Fix present with migration; tests guard both heuristic directions. No browser. **Provider drift left open:** the Anthropic copy of `create_pension.md` lacks `scheme_status` entirely (0 occurrences vs 2 in the xAI copy) — a Rule 20 second home, dormant only while xAI is the live provider. |
| **W-0033** | Author states "**Not browser-verified**". More seriously, the test is a **characterisation** test the author expects to fail later, and the "Not provided" branch it covers is stated to be **correct and unreachable** — the answer to "would this assertion still pass if the mechanism did nothing?" is **yes**. |
| **W-0035** | Fix present on web and `/m`; tests guard (`income_source` flips `calculated → profile`, 90000 → 55000). **No browser evidence at all**, and the consumer chain is explicitly "**Traced, not tested**" — the item exists because every projection runs on this figure, and that the projection moves was never shown. |
| **W-0036** | Excellent live service read-back on the real row (`isInPayment FALSE`, income `35,000 → 0.00`, required `116,250 → 90,000`). No browser interaction; acceptance 9 is explicitly left to the persona-tester. |

**W-0034 needs saying separately.** The author wrote: *"Browser-verified on `/m`, both
accounts — NOT DONE … the section is component-tested but has not been seen in a real
browser by anyone, and I am not claiming otherwise."* Acceptance item 7 is marked
**BLOCKED**. That is exactly the entry `08-process.md` §2.3 calls valid and expected, and
it is the single most creditable piece of reporting in this batch. **CANNOT CERTIFY —
and the author is the reason I know why.** (Batch total for CANNOT CERTIFY: 18.)

### CERTIFIED (11)

**W-0009** · **W-0013** · **W-0016** · **W-0018** · **W-0021** · **W-0022** · **W-0023** ·
**W-0026** · **W-0027** · **W-0028** · **W-0029**

What these eleven have in common, and the others do not: the fix is present, the test
would fail if the mechanism did nothing, **and the item records a real interaction with a
database row read back afterwards.** Specifically —

- **W-0009**: holding 32 before/after — `ticker NULL → VGLS80`, `ocf_percent 0.0000 → 0.2200`.
- **W-0013**: `POST /api/savings/accounts → 201`, row `id 29, ownership_type=joint, ownership_percentage=50.00, joint_owner_id=16`, then deleted.
- **W-0023**: full wizard, then `BEQUEST id=17 … amount=10000.00 will_document_id=8`.
- **W-0026**: new policy → `policy_end_date = 2040-01-01` (was NULL), confirmed on web **and both `/m` policy screens**.
- **W-0027**: `joint=Y trust=Y beneficiaries="Sarah Jones: 34%, William Jones: 33%, Charlotte Jones: 33%"`, web + `/m`.
- **W-0029**: goal id 59 and life event id 82 created through the forms, `/m` confirmed showing "Target date passed".
- **W-0022**: `GET /api/user/letter-to-spouse` returning the HSBC block **and** `GET /api/estate/letter-validation` returning zero `/outstanding/` warnings in the same session — the two mechanisms proven to stop contradicting each other.

**W-0018** is certified on a different basis and it is worth stating why: it changed a
docblock and added a test, no runtime behaviour on any surface. **A browser gate on a
comment would be ceremony.** The behavioural pin genuinely guards (two legacy subscribers
differing only in `users.tier`).

**W-0021** likewise — a Rule 9 acronym expansion, verifiable in full from the source
string. `RPT` survives only in an HTML comment and a CSS comment.

**W-0028** is certified with one gap named: the author quoted the full `/m` page text for
nine events reconciled by hand against the DB, but **the screenshot call timed out twice**
and none was captured. The page text is the stronger artefact of the two; I am not
withholding certification for a missing image when the reconciled content is present.

---

## BATCH B — W-0039 … W-0161 (34 items)

Fix-presence verified against the tree for all 34. **All 34 are present as claimed** —
this batch has no W-0012. What it has instead is a near-total absence of browser
evidence: **31 of 34 record none at all**, and most say so openly, in the author's own
words, because a standing instruction told fix agents not to close their own Rule 14
loop. That instruction is correct (`08-process.md` §2.4). Its consequence is that the
loop was left for me, and I could not close 31 of them in one session.

### CERTIFIED (3)

**W-0046 — backfill will bequests.** A CLI command, so the browser is not the right
instrument and its absence is not a gap. The evidence is what this kind of work should
produce: a `--force` run against the local database with a verbatim before/after table,
**and a second run proving idempotency** — 18 bequests before and after, 12 hand-made
rows untouched. Production explicitly out of scope and untouched. The one
`REVIEW`-flagged duplicate-charity row (Sarah Mitchell, 24) is a stated decision, not a
defect left lying.

**W-0125 — migration test leaks fixtures.** Test-infrastructure work, judged on
test-infrastructure evidence: a MySQL binlog reconstruction of the leak, and a post-fix
directory run leaving all five tables empty. `:166` asserts on **identifiers** — emails,
scheme names, dedup keys, user ids — not counts, which is the difference between a test
that detects a leak and one that agrees with it. Residual named honestly: the standing
`afterEach` detector was deliberately not added (shared config, mid-run collision risk),
so the class can recur silently. That is a residual, not an unmet criterion.

**W-0080 — education-level acronyms. Certified, and my suite run is what closed it.**
The author recorded an explicit **"I COULD NOT TEST THIS"**:
`tests/Unit/Database/ProfileEnumColumnsTest.php` could not be run because the database
`laravel_testing_e` did not exist. **In my full-suite run it ran and passed:**

```
   PASS  Tests\Unit\Database\ProfileEnumColumnsTest
  ✓ it pins HEALTH_STATUSES to the users.health_status column            0.04s
  ✓ it pins SMOKING_STATUSES to the users.smoking_status column          0.05s
  ✓ it pins EDUCATION_LEVELS to the users.education_level column         0.04s
  ✓ it records that smoking_status is NOT NULL, which the request rules…  0.04s
```

The labels have one home (`ProfileEnums::EDUCATION_LEVEL_LABELS`), that home is pinned
to the live columns by a test I watched pass, and both frontend constant files compose
from it. **This is what the gate is for** — a gap the author correctly refused to paper
over, closed by the reviewer who had the instrument they lacked. The `a_level` wording is
flagged as a revertable judgement call, which is a product note, not an acceptance
failure.

### REJECTED (1)

**W-0138 — `/m` estate omits chattels and shows no tax figure. REJECTED.**
**Unmet criteria: faults 2 and 3, and acceptance 5.** Fault 1 is genuinely fixed —
`CrossModuleAssetAggregator::getChattelAssets():192` and `calculateChattelTotal():327`
exist and are wired in. But the author's note says, in terms: **"Do not close."**
Fault 2 (individual-vs-household basis) and fault 3 (no Inheritance Tax figure on `/m`)
are **explicitly untouched**, and acceptance 5 — rebuild `npm run build:mobile` and check
both accounts in a browser — was not done. A third defect is reported in passing and left:
`NetWorthService::getJointAssets()` misses every asset class where the user is the *joint*
owner rather than the primary.

I am rejecting rather than gating because the item's own author states the work is
incomplete against its own acceptance. **That is not a missing-evidence problem; it is
unfinished scope, and `status: handoff` was the wrong field to set.**

### CANNOT CERTIFY (30)

**Grouped by what is actually missing, because the reason matters more than the list.**

**(a) Blocked on an outstanding CSJ or product decision — 7.**
`W-0111` (partner linking unsupported by design — ruling recorded, but no browser) ·
`W-0112` (open: should `users.name` be rejected loudly) · `W-0114` (making
`partner`/`step_child` native enum values deferred to CSJ with a consumer sweep
attached; **and a live defect noted-not-fixed — editing a step child re-opens the form
showing "Child"**) · `W-0126` (acceptance 5 not done, import site split to W-0127 for a
product call) · `W-0140` (acceptance 1 **superseded** by a team-lead decision — the item
was never restated, so it is being judged against criteria that no longer describe the
intent) · `W-0157` (finding 2 **deliberately left live** — an unconditional consequence
in Fynla's own voice, blocked on W-0153; a recorded exposure, not an oversight) ·
`W-0161` (**acceptance 3 unmet — existing joint liabilities Fyn created before the fix
are still stored 100/0**; deferred to a sweep with W-0043 as CSJ's call).

**(b) A required specialist review was never run — 3, and these are the serious ones.**
`W-0049` — server-side cookie consent. **Acceptance 5 requires `security-reviewer` on the
pre-auth write path and the author states it was NOT DONE.** A pre-authentication write
path is close to the worst place to skip that review.
`W-0091` — Business Property Relief cap. `tax-compliance-reviewer` has not reviewed it;
Agricultural Property Relief is **not implemented** ("not implementable as the schema
stands") with the user-facing disclosure of that gap pushed to W-0466.
`W-0136` — RNRB taper. The reviewer confirmed **the arithmetic before the fix, not the
implementation after it**, and a second taper implementation finding
(`HouseholdPlanningService.php:921-924` — a hardcoded `/2` and `$ihtConfig['rate'] ?? 0.40`
against a **NULL key** at two sites, which is a Rule 2 hardcoded-tax-value breach) is
recorded and unactioned.

**(c) The item's own headline is relocated, not closed — 2.**
`W-0135` — the £103,206 two-login divergence is **moved into W-0137, not fixed**; the
author says so and says fixing it moves the £1,539,360 pin. `W-0154` — real browser
evidence exists (both columns transcribed row by row, and it **caught a regression by
looking**: Sarah's home allowance rendering £0 because `IHTController` was not publishing
the new fields). But the author flags the criterion that matters as **NOT DONE** —
"every figure hand-checked against `tests/Persona/peak_earners.md`" — with the exactly
right observation that *this is the acceptance that distinguishes 'adds up' from
'correct'*. A table can reconcile perfectly and still be wrong. Separately,
`charitableBequestFingerprint():2102` is **still `Will::where('user_id', $user->id)`** —
a household-scope bug surviving inside the household-scope fix.

**(d) Evidence simply absent on a user-facing surface — 18.**
`W-0039` · `W-0041` · `W-0044` · `W-0045` · `W-0047` · `W-0051` · `W-0052` · `W-0053` ·
`W-0100` · `W-0101` · `W-0103` · `W-0113` · `W-0115` · `W-0121` · `W-0122` · `W-0132` ·
`W-0134` · `W-0143`

Four of these deserve a specific note rather than a bullet:

- **W-0044** is the native Will Builder route. The author wrote **"I COULD NOT TEST THE
  BUTTON"** and the evidence is `BUILD SUCCEEDED` only. This is Gate A in its pure form:
  `WebHandoffClient.swift` and `EstateView.swift` were genuinely changed, so **CSJ device
  verification is required before merge** and no amount of desk work substitutes.
- **W-0045** is a palette item, and the grep is strong evidence —
  `(bg|text|border)-(blue|green|red|teal)-[0-9]+` returns **0** across the Trusts
  components. But the acceptance keeps an unticked visual-confirmation box over four
  screens, and **the root cause is still in the tree**: `tailwind.config.js:9-12` still
  safelists `blue-/green-/teal-/red-`, parked as W-0048 by CSJ. The rule is unenforced,
  so the next component can reintroduce it silently. 807 occurrences remain elsewhere in
  `resources/js/`.
- **W-0052** — the fix (a 28-entry drop list in `InvestmentAccountNormaliser:44`) is
  present and correct. But one acceptance criterion — *"a 500 on this path surfaces an
  error to the user instead of a silently open modal"* — **is not addressed anywhere in
  the working notes.** It may well be satisfied by W-0261's error handler; nobody has
  said so, and I will not certify a criterion by inferring a connection the author did
  not make.
- **W-0115** — the Rule 20 consolidation is verifiable and complete
  (`grep formatRelationship resources/js resources/mobile` → **nothing**). It is gated on
  its own acceptance criterion 3, which asks for the casing to be checked on both
  surfaces *because the helper deliberately returns lowercase and each surface applies
  its own capitalisation*. That is a criterion that can only be met by looking.

---

## PRIORITY 2 — today's items, and the spouse-linking CRITICAL

### The spouse-linking security cluster — W-0344, W-0347, W-0348, W-0349

One fix commit, `70c5014da`, 22 files, +1720/−138. **This is the best-evidenced work on
the board**, and three of the four still cannot be certified. The reasons are worth
separating carefully, because "good work, blocked" and "incomplete work" are different
verdicts and this cluster contains both.

**W-0347 — CRITICAL: linking wrote the other person's row and forged their consent.
CANNOT CERTIFY. Gate: `compliance-lead`, still open.**

The fix is real and I checked it. `SpouseLinkingService::linkExistingSpouse():254-278` no
longer writes the other account at all — the whole unlinked branch is now three lines that
touch only the *caller's* row plus a pending invitation. The old writes to
`$lockedSpouse->spouse_id / marital_status / annual_employment_income` and five address
fields are gone. `establishAcceptedLink():314` sorts ids and locks the lower first, and
copies neither income nor address.

The authorisation is a query scope, not a policy:
`SpousePermission::where('spouse_id', $user->id)->where('status','pending')` — the column
IS the invitee, so only the invitee can find their own invitation, and no unscoped lookup
path remains. That is sufficient, and I would rather have it than a policy class.

**The test proves refusal, not merely success**, which is the thing most security tests
get wrong: `SpouseLinkConsentTest.php:236` creates a third-party bystander, POSTs accept as
them, asserts **404** *and* asserts `$this->attacker->fresh()->spouse_id` is still null.
Under the old code the link was already forged at invite time, so that second assertion
fails regardless of the 404 — it discriminates.

Rule 19 is genuinely honoured: `/m` gets a real counterpart —
`resources/mobile/views/SpouseSharing.vue`, 166 new lines, plus router and settings wiring —
not a claim that the backend covers it. Browser evidence includes a click-and-submit with
the DB read back **on the exact columns the defect used to write**.

**So why not certified.** `gate: compliance-lead` is set in the frontmatter and acceptance 4
requires that sign-off, which has not happened. A fix to *forged consent* is squarely
compliance's competence, not mine — I clear within competence or I flag, and this is
outside it. Also open: the `/m` accept/decline branch was never rendered against a live
pending invitation ("I COULD NOT BROWSER-TEST THIS ONE BRANCH" — there is no unlink UI to
stage one), iOS has no accept/decline screen at all, and the author states plainly
**"Not deployed anywhere."**

**W-0348 — endpoints returned a raw Eloquent User model. CERTIFIED.**
Both sites now return five fields (`FamilyMembersController::spouseSummary():265-272`,
`SpousePermissionController::counterparty():44-57`), and the invitation branch returns **no
counterparty at all**. The test is the strongest in the cluster and the right shape: it
asserts the **absence of the victim's actual data** in the flattened JSON —
`not->toContain('82000')`, `'1979-04-02'`, `'9 Private Road'`, `'BS1 9ZZ'`, `'Bristol'` —
and the fixture demonstrably sets every one of those on the victim, so the pre-fix raw-model
response contained them all. Backend-only response shaping, so it reaches all three surfaces.

One qualification I am recording rather than hiding: acceptance 3 asked for a sweep for
other raw-model returns of another user, and **no sweep is recorded in the item.** An
independent grep of `app/Http/Controllers/` found none surviving. I am certifying on that
independent check, not on the author's claim — because there is no author's claim. The
item should carry the sweep result.

**W-0349 — the family-members endpoint is an account-enumeration oracle. REJECTED.**
**Unmet criterion: acceptance 2 — the four outcomes do not collapse to one message.**

The throttle half is genuinely done and well judged: a per-user key
(`'spouse-invite:'.$currentUser->id`, 5/hour → 429) deliberately kept off the shared per-IP
bucket, with a control test proving an ordinary child can still be added.

The enumeration half is not. **Two of four outcomes collapsed; the registered-vs-
unregistered pair still differs** — an unregistered address returns `created: true` +
"Spouse account created…", a registered one returns `invitation_pending: true` +
"Invitation sent…". Anyone can still ask this endpoint whether an email has an account.

**And the test named after the criterion asserts the defect.**
`SpouseLinkConsentTest.php:138 it('does not confirm the address is even registered')`
asserts `expect($unregistered['created'] ?? false)->toBeTrue()` — it asserts **the
distinguishing key is present**. Its own comment concedes "The remaining difference is
deliberate." A test that pins the behaviour its title says is forbidden is worse than no
test: it will hold the oracle in place through every future refactor. Acceptance 3 is also
explicitly not done.

I am rejecting rather than gating because this is not missing evidence — the behaviour is
present, tested, and contrary to the item's own acceptance.

**W-0344 — one-sided link discloses the other account's policies. CANNOT CERTIFY.**
The gate is `LifeCoverReach.php:101-107` and `User::hasReciprocalSpouseLink():494` does a
real reciprocal existence query, not a column read. Tests discriminate on counts and totals
that move, not on the `0.0` alone. The author's reason for no browser evidence is correct
and should be read as a model of the genre: **"Browser cannot verify the blocked states:
the persona has no deleted, one-sided or unreciprocated link, so there is no screen to look
at."**

**What blocks it is acceptance 3, and it is much larger than the item looks.** The census
was delegated to **W-0350, which is untouched: 53 `spouse_id` consumer sites, of which only
three use the reciprocity rule.** The author's own earlier note calls this fix *"a speed
bump rather than closure"*. Three doors are locked and fifty are not.

---

### Today's estate and tax items — W-0463, W-0464, W-0465, W-0466, W-0467, W-0469

**All six: CANNOT CERTIFY. Five carry an open gate in their own frontmatter.**

| Item | `gate:` | Blocking criterion |
|---|---|---|
| W-0463 | `tax-compliance-reviewer` | acceptance 7 |
| W-0464 | `tax-compliance-reviewer` | acceptance 4 |
| W-0465 | `tax-compliance-reviewer` | acceptance 5 |
| W-0466 | `compliance-lead` | acceptance 4 |
| W-0467 | `compliance-lead` | acceptance 3 |
| W-0469 | `null` | Gate B, and Gate A |

**The systemic finding, and it is the one I would put in front of CSJ first.**

The two commits on this branch — `88494e0fd` and `8f09eaddc` — change Inheritance Tax
arithmetic and **have not been through the tax-compliance gate they are explicitly gated
on.** That matters far more than usual here, because of what the record shows about this
particular branch:

- Round one of the review: **18 findings**, ten of them fixed in `a1d36b90b`.
- Round two, re-reviewing `a1d36b90b`: **two HIGH regressions the fix itself introduced** —
  R1, survived potentially exempt transfers cumulating, **£110,000 of invented tax** on two
  £300,000 gifts; R2, the F2 taper fix double-counting business relief — plus R3, R4, R5.
  All fixed in `19bd1c83f`.
- A further re-review then found **R6 → W-0465**: the projection applied no business relief
  at all.

**Three rounds of review, three crops of defects, each found in the fix for the last one.**
The fourth round has not happened, and the two commits awaiting it are the newest. On this
evidence the prior that they are clean is poor, and it is not a prior I am entitled to
substitute for the reviewer.

**The work itself is strong, and I want that on the record too.** Specifically:

- **W-0463's coverage guard is the best single artefact on this board.**
  `tests/Feature/Tax/ConfiguredRulesHaveConsumersTest.php` **found the three orphans
  independently, before being told what to look for**, is mutation-checked (removing the
  `getBusinessRelief()` call turns it red), and its author discovered and fixed a false
  positive caused by deriving accessor names from key names (`getPETRules()`, not
  `getPotentiallyExemptTransfers()`) — with the right lesson attached: *a guard that cries
  wolf gets switched off.* **It passed in my run:**
  `✓ it has a consumer for every configured inheritance tax rule` /
  `✓ it keeps the exclusions register honest`. So did all nine
  `BusinessPropertyReliefCapTest` cases including the £6m worked example.
- **W-0465 pre-empted the exact trap this brief warns about.** The author records that
  `IHTPlanning.vue`'s fallback to the *current* deduction **was a Collision** — it made the
  projected column show the right number for the wrong reason, *"so a browser check before
  the fix would have looked correct."* They removed it. I verified: `IHTPlanning.vue:1590`
  now falls back to `0`, not to the current figure. They also found that a taper test needs
  a fixture with both a main residence and a direct descendant, or `rnrb_status` is `'none'`
  and **every taper assertion passes against a band that was already zero** — and stated
  that a pre-existing test in the same file **sits in exactly that trap and is not fixed
  here.** That is a Fixture-variant decoy, found and declared rather than left.
- **W-0464 turned a one-off into a standing rule.** CSJ's *"/m MUST NEVER work anything
  out"* was applied beyond the item: three more `/m`-side calculations were consolidated
  server-side, and **one of them disagreed with the backend** — `/m` took contribution
  percentages first where the backend took the flat monthly amount first, so a pension
  recording both was described differently depending which screen you were on. That is
  Rule 20 done properly.

**W-0463 has two substantive gaps beyond the gate**, both self-declared:
**taper relief on failed potentially exempt transfers is still not applied** (acceptance 3),
and the guard **reports the taper table as covered anyway**, because the PET rule group is
read for its *window*. The author names this as "a real weakness of a reference-based
guard" in the test's own docblock. **A coverage guard that agrees with the gap it exists to
find is the Collision variant one level up**, and it is the single thing on this board most
likely to let a future regression through quietly.

**W-0466 carries a residual that changes who the caveat protects.** The wording CSJ settled
is implemented and published once by `IHTCalculationService` (`unmodelled_relief_caveat`),
rendered on web at `IHTPlanning.vue:401-403` and on `/m` at
`resources/mobile/views/modules/Estate.vue:20` — I checked both. But **the trigger is
business interests only**, because agricultural property has no representation in the
schema (`assets.asset_type` is `enum('property','pension','investment','business','other')`).
**A farmer holding land and no company still sees nothing** — and farmland is the case
where the omission of Agricultural Property Relief overstates tax by roughly 40% of the land
value. The author states this rather than burying it, and it is not closeable without a
schema change.

**W-0469 — `/m` estate handoff. CANNOT CERTIFY on evidence, not on substance.**
The decision is recorded (CSJ: an honest summary that hands off to web), acceptance 4 holds
by construction — there is no arithmetic left on the card — and the `W-0044` allowlist guard
**fired on the first run and was right to**, catching a destination added to the PHP enum
without its native mirror, which is exactly how `estate_will` went missing. Good machinery
working as designed.

But acceptance 3 says the `/m` screen *says* the detail lives on the web app and links
there, and **nobody has seen that card in a browser.** I cannot close it either: `/m` is
verified on csjones, csjones runs `dev` at `5c556e252`, and this work is on two commits that
are **not on dev**. Rebuilding the `/m` bundle is the coordinator's call, not mine. It also
touched `ios-native/` (the Swift enum and two test assertions), which are XCTest and
therefore **not covered by my Pest run** — Gate A applies.

---

## BATCH C — W-0137 … W-0257 (29 items), including the rest of Priority 1

**A correction to my own Finding 0, made before it misleads anyone.** I said the evidence
lives inline in the board items. For the investment-projection cluster that is wrong: the
real evidence is in the **branch document**, `F-0024 §6`, and it is substantial —
before/after at four horizons on both accounts, driven through the MFA gate with codes
taken from the database, chart y-axis scaling checked, **and the persona state restored
afterwards (Sarah's ISA back to `medium`)**. The items point at it; I had not opened it.

That is the location problem, not an absence problem, and it changes five verdicts. It is
also the strongest argument for the constitution's `evidence/` directory: evidence nobody
can find is functionally evidence nobody has.

**By contrast `F-0018` says, in terms: *"Not browser-verified, by instruction: the tester
closes that loop next cycle."*** So for the cash-projection cluster the absence is real.

### CERTIFIED (6)

**W-0242 — `LifeStageService` throws on a nonexistent column.** A real HTTP interaction
(`GET /api/life-stage/progress` as David, 200, no SQL in the body) **plus the sharper
half: the removed line still throws when run directly against the live database**, which
proves the path was genuinely reached rather than merely not crashing. The sweep is
complete and counted — 354 aggregate call sites inspected, zero query-builder aggregates
over nonexistent columns remaining. No unmet criteria.

**W-0239 — a 24-hour cache served a wrong dashboard.** No browser interaction, and none is
the right instrument for a cache-invalidation mechanism. The evidence is a live probe:
touching `updated_at` on David's joint savings row 53 **and** on policy 7 each cleared
`mobile_dashboard_17` — the second case being one that neither deleted observer could have
reached, which is what makes it discriminating rather than confirmatory. Two deliberate
non-changes are recorded with reasons (`User` not observed because it is written on every
login; the TTL not shortened as a substitute for invalidation).

**W-0251, W-0254, W-0256, W-0252 — the investment projection cluster.**
`F-0024 §6` carries the browser evidence for the first three: David's five-year figure
**£4,650 → £86,944** with the £80,000 of life-event withdrawals annotated on the chart;
Sarah's capital **£85,000 → £132,500 so the card and the projection finally agree**; her
36-year p20 **£1,577,731 → £261,740**. A "subset never exceeds the set" table closes the
arithmetic. **The caption now reconciles to within volatility drag** — σ²/2 = 0.1688²/2 =
1.42% — which is the difference between a number that adds up and a number that is
explicable.

**W-0252** carries its own: Sarah's ISA 13 changed Medium → High **through the real account
edit form**, `risk_preference=high` with `updated_at 21:57:07` read back, page reloaded,
caption 5.54% → 7.46%, projection **£158,918 → £146,328**, persona restored. Its declared
gap — "could not be verified on David" — is an environment collision (another agent had
pushed account 26's holdings to 105%, silently disabling the form) that was **raised as
W-0257 rather than worked around**. That is the right response to a blocker.

### REJECTED (1)

**W-0190 — joint expenditure declares 50/50 but splits the manual spend 100/0. REJECTED.**
**Unmet criterion: the defect still reproduces through the only door `/m` has.**

The service-layer fix and migration `2026_08_22_000200` are real, and team-lead verified
the persona rows moved (£2,450/NULL → £1,225/£1,225). But the author states plainly that
**`CoordinatingAgent::handleSetExpenditure()` — the Fyn path — was NOT fixed and still
writes one account at 100%**, and adds the fact that decides this verdict: **on `/m` that
is *the only* editing door.**

So a `/m` user editing their expenditure today still produces exactly the 100/0 split this
item exists to remove. Rule 19 is not satisfied by a backend fix the surface cannot reach,
and Rule 20 is not satisfied by fixing one of two writers. It is filed as W-0202 and
flagged as "the one question the author could not answer" — which is honest, and still
leaves the item's own headline live.

### CANNOT CERTIFY (22)

**Two that need naming individually, because the reason is not "no browser".**

**W-0244 — retirement reports "not set up" for a £500,000 household. CANNOT CERTIFY on
Rule 20, despite having the best browser evidence in the batch.** A dated, superseding
"BROWSER VERIFIED — both accounts, web and `/m`" block; the `/m` retirement hero replacing
"£0 a year"; the £35,000/year guaranteed income on the web dashboard. All good. But the
author declares: **"Guaranteed income" still has two implementations** — the backend via
`PensionProjector` (revalued) and `PensionList.vue` computing it client-side from raw
columns — and **they agree on this persona only because Sarah's scheme happens to carry a
particular `inflation_protection` value.** Two mechanisms that agree by coincidence are the
W-0154 disease with the symptom temporarily absent. Under Rule 20 consolidation is part of
the fix, so the fix is not complete.

**W-0237 — "total balance owed" sums full balances. CANNOT CERTIFY on Rule 19.**
The web evidence is the strongest single measurement in the batch: `/net-worth/liabilities`
as David, **TOTAL BALANCE OWED £365,000 → £170,500**, monthly payments £1,950 → £900, the
Manchester label corrected from "Joint (40.00% yours)" to "Tenants in Common (40% yours)".
But `/m` was never checked — **and the cycle-4 tester brief carries this forward as a named
outstanding item: "David's `/m` liabilities screen must render £170,500 — never verified;
the agent that owned it recorded 'I COULD NOT VERIFY THIS'".** The gap is known, written
down, and still open.

**One overclaim I am flagging rather than accepting.**
**W-0228** states "Acceptance 5 ✅ Verified on both accounts" while its Evidence section
names **only a test file** — there is no click, fill, submit or screenshot anywhere in it.
This is the single instance on the board of a **ticked browser criterion with no browser
behind it**, and it is precisely the failure mode the brief warned me about, inverted:
not a criterion missing from the list, but a criterion on the list marked met without
evidence. Treat as an assertion plus a live-DB measurement. (Its accepted limitation —
that the model cannot express a mortgage in one spouse's sole name against a jointly-owned
property, knowingly accepted by CSJ — is correctly recorded and I am not re-raising it.)

**The rest, grouped by reason.**

| Reason | Items |
|---|---|
| **Browser verification withheld by standing instruction, tester's loop never run** | W-0172 · W-0174 · W-0175 · W-0176 · W-0177 · W-0186 · W-0187 · W-0206 · W-0207 · W-0210 · W-0236 |
| **An acceptance criterion has no persona coverage and needs a constructed case** | W-0203 (acceptance 3 — the household holds zero `liabilities` rows) |
| **An outstanding CSJ decision** | W-0217 (acceptance 2 explicitly NOT MET and argued to be *correctly* unmet — the 20th percentile is hump-shaped in risk because volatility widens the downside faster than expected return lifts it; raised as **W-0259** with a measured table) |
| **A migration written and left unapplied** | W-0173 — the author's own warning: *"Whoever verifies this must apply it first, or W-0173 will read as unfixed."* I have no database access and could not establish whether `2026_08_22_000100` has run. **State unknown is not a pass.** |
| **Acceptance met only at the client, enforcement still open** | W-0257 — the browser evidence is the most detailed in the set, but the author states acceptance 3 is met only by "the form copes", with server-side enforcement open as **W-0321**: *"a client-side guard is not enforcement, and `/m`, native and Fyn capture all post to the same unguarded endpoints."* **W-0322** — a silent, destructive replacement of every holding with a single 100% Cash row — is the consequence of the same gap. |
| **Rule 19 unfinished after a bundle rebuild** | W-0238 — genuinely strong evidence (both accounts, **named screenshots that exist in the repo root**, `/m` reading David's corrected £99,750/£172,500), but `/m` could not render Sarah's guaranteed-income headline against a bundle dated 2026-08-21. `public/m-build/` has since been rebuilt (2026-08-23 20:21); **the re-verification of Sarah has not been done.** |
| **The fix is real in code, the browser loop was never closed** | W-0137 · W-0188 · W-0255 |

**W-0137 and W-0188 deserve their own sentence, because the code is right and the item
records nothing.** Both read as though never worked — no working notes, no test, no
evidence. They were worked. I verified it: `HouseholdCashFlowProjector.php:169-175` floors
the balance at `0.0` and accrues the deficit to `shortfall`, and
`IHTCalculationService.php:791-796` publishes `projected_cash_shortfall` with a comment
naming W-0137 — **which is exactly acceptance criteria 1 and 2, implemented as specified
rather than by capping the display.** The class being *household*-scoped is the structural
answer to W-0188. What is missing is only criterion 5 (both accounts, table expanded), and
`F-0018` states it was withheld by instruction.

**W-0255** is narrower than it looks: the corrected percentile *values* are browser-
evidenced in `F-0024 §6`, but the subject of the item is the *band shape* — a 5th
percentile sitting below anything simulated, and the first two years pulled toward the
start value. **A table of p20 figures does not show the shape of a band.** The chart's
y-axis scaling was checked; the band geometry was not.

---

## BATCH D — W-0261 … W-0384 (22 items)

**This batch is different in kind and it should be said plainly: it is the only batch on
the board where browser verification was routinely done, and done well.** Several items
here demonstrate techniques the rest of the board would have benefited from.

Three worth copying:

- **W-0261** forces `app.debug` **ON** in its disclosure test, *because a debug-off run
  passes against the old code and proves nothing*. The author identified the decoy in
  their own test and disarmed it.
- **W-0274** proves a `/m` fix shipped by **grepping the built bundle for a string that
  can only exist if the change landed** (`ms-acct__share`), having first rejected
  `full_balance` as a discriminator because it legitimately survives. A stale `/m` bundle
  fails by *agreeing with you*; this is the only reliable answer to that.
- **W-0273** verified a disclosure was not clipped by **DOM measurement rather than by
  eye** — `clientHeight 32 === scrollHeight 32`, `webkitLineClamp: none`,
  `overflow: visible`, four uncut lines at 390 × 844. "It looked fine" is not a finding.

Two items corrected the premise they were dispatched on rather than delivering against it:
**W-0273** (dispatched as stale stored rows; measured that the figures were being
recomputed wrong live — *"had the premise been taken on trust, the work would have been a
recompute migration that measures green and leaves the live defect"*) and **W-0331**
(retracted the alleged double count as unreachable, which matters because W-0280 governs a
59-site sweep built on it).

### CERTIFIED (12)

**W-0261 · W-0262 · W-0263 · W-0271 · W-0273 · W-0326 · W-0331 · W-0332 · W-0339 ·
W-0341 · W-0382 · W-0384**

The load-bearing evidence, briefly:

- **W-0261** — form filled with both "(Optional)" fields blank, submitted, **row 68 read
  back with both `0.0000` and £1,250 = 100 × £12.50, no SQL on the page**; fault 2 proven
  with a *genuine* `QueryException` (a 50% yield overflowing `decimal(5,4)`), not a
  simulated one.
- **W-0262** — pension 9 through the real form, `medium → upper_medium`,
  `has_custom_risk false → true`, `current_fund_value` intact. **The browser pass caught a
  regression the fix itself had introduced** (a legitimate save 422'd because
  `salary_sacrifice: null` was newly exposed), which was then pinned by a test posting the
  browser's verbatim 30-field payload. Two independent mutations recorded.
- **W-0263** — a **12% mortgage rate entered through the real form and saved**:
  `PUT /api/properties/9` 200, `PUT /api/mortgages/8` 200, row holds
  `fixed_interest_rate: 12.0000`. The drift guard
  (`ValidationMaxFitsColumnPrecisionTest`) was **proven red before it was trusted**, and
  twelve columns were widened with **none capped** — honouring the reasoning that capping a
  mortgage rate at 9.9999 turns a crash into a wrong answer delivered politely.
- **W-0271** — Sarah on `/risk-profile` **25.3 months**, dashboard 25.3 / 6 months £31,030,
  **and `/m` reading the same in the same pass**. Rule 19 satisfied rather than asserted.
- **W-0326** — **certified by cross-reference, which I want to show my working on.** The
  item contains no post-fix browser check of its own. W-0263's journey supplies it:
  `PUT /api/mortgages/8` returned **200 where it previously returned 422 — and 422 was
  W-0326's exact symptom.** The proof exists; it is filed under the item that tripped over
  it.
- **W-0331 and W-0339** are certified on **honest null results**. W-0331's finding is that
  no user's tax moves by a single pound, so there is no screen to photograph, and it says
  so. W-0339 removed a phantom column (`mortgages.end_date` → `maturity_date`, **zero
  occurrences remain**) and declares the decoy condition outright: this persona's mortgages
  mature inside the horizon and its projected RNRB is already tapered to £0, so *"the wrong
  branch and the right one both give £0."* Declaring that is the opposite of hiding behind
  it.
- **W-0382** — the non-owner told to "contact your provider" about a contract she has no
  relationship with. Not browser-verifiable on this persona and the item says so, but
  demonstrated in a **rolled-back transaction** with `in_trust` verified back at 1
  afterwards, plus mutation M7. Its framing note is the one I would put on the wall:
  **"Unreachable is not absent"** — joint-life-and-not-in-trust is ordinary in real data
  and simply absent from `peak_earners`.
- **W-0384** — verified **from the non-owner's account on both surfaces, which is the only
  account that can discriminate**, with identity read from `GET /api/auth/user` on each
  surface's own token rather than from a figure. It also corrects its own framing: *"two of
  the three HIGH gaps were false, not three"* — the £72,000 income-protection figure is
  genuine and correctly survives.
- **W-0332** states its own limitation instead of overclaiming: a 50/50 account proves only
  that neither spouse is charged the whole £4,500; **the asymmetric discrimination lives in
  the tests, at 70/30.**

### CANNOT CERTIFY (10)

**Four are Rule 20 residuals — the fix reached one mechanism of several. These are the
most likely to be mistaken for finished work.**

- **W-0272 — "a linked spouse is assessed as childless".** Browser-verified on Sarah's own
  login: `/risk-profile` reads **Dependants 2 · Lower-Med** and the factor page **names
  William and Charlotte**. But **eight other consumers still ask the same question with the
  old `user_id` query** — protection plans, the AI memory retriever, savings, estate, and
  the Fyn prompt builder. Routed to W-0275 for scope discipline. So `DependantsReach` is
  "one home" for one of nine callers, and **the item's own title is still true in eight
  places, including the one that talks to the user.**
- **W-0264** — the investment readers were consolidated onto
  `RiskPreferenceService::getProductRiskOverride()` and the browser proof is genuinely
  discriminating (`has_custom_risk = 0` in the setup, allocation 50% → 90% equities). But
  **the pension readers were deliberately not routed to that home** and still gate on the
  old pair, so two mechanisms answer "what is this product's risk". Two acceptance boxes
  remain unticked, including whether `has_custom_risk` should exist at all.
- **W-0342** — `policy_assessment` **0 entries → 5** is a real before/after. Two gaps: it
  **opened a new wrong answer** while fixing this one (routed to W-0382, since fixed —
  good), and **`EstateAgent::analyze()`'s cache is never cleared**, which means the
  corrected answer may not reach a user whose cache is warm.
- **W-0336** — correct on its own terms and correctly unphotographable (£0 on this
  persona). But **the headline still reads the one-leg mortgage version (W-0338), so the
  projection and the headline now use different readers** — the precise divergence this
  family of items exists to eliminate, reintroduced one layer up.

**Three are open decisions.**

- **W-0278** — the three blocked link states are covered by tests because the persona has
  none of them, which is the right answer. But **a fourth state — a refused or
  never-accepted `SpousePermission` — still reaches**, decided deliberately and raised for
  CSJ as W-0345. A disclosure boundary with a knowingly open edge is CSJ's call, not mine.
- **W-0383** — the browser evidence is excellent and from both sides in one session
  (David's `/m` shows `VIT-LT-456789` and the beneficiary free-text **naming the couple's
  two children** — exactly the payload the rule exists to contain; Sarah's `/api/protection`
  returns null for both). But **acceptance 2 is an open question for CSJ**: `premium_amount`,
  `premium_frequency`, the policy dates, `policy_term_years`, `indexation_rate`,
  `start_value` and `decreasing_rate` all still ship to the non-owner, unruled.
- **W-0274** — browser-verified on both accounts, web and `/m`, with the bundle proof
  described above. **Acceptance 4 is explicitly NOT done and I confirmed it is still live:**
  `SavingsActionDefinitionService.php:436` and `:514` still read the old mechanism. A
  fourth answer to "how big is the emergency fund" survives in the service that defines
  savings actions.

**Three more.**

- **W-0322 — the destructive one, and the backend hazard is untouched.** The client-side
  fix is real (`AccountForm.vue:1037` and `DCPensionForm.vue:1327` both `delete` the
  holdings key) and the browser proof was **well chosen**: run on account 13 rather than 14
  precisely because 14's only holding is the auto-Cash row the form filters out and *"could
  not discriminate"*. But **the backend behaviour is confirmed still present** — an empty
  holdings array still means "delete everything", and "clear them all" and "say nothing"
  remain indistinguishable at the endpoint. Acceptance 3 and 4 are unticked and open. Read
  with W-0257's finding that **`/m`, native and Fyn capture all post to the same unguarded
  endpoints**, the silent destructive path is still reachable — just not from the one form
  that was fixed.
- **W-0333 — CANNOT CERTIFY on Gate B, and this is the largest unphotographed number on the
  board.** The fix is right and both gates it needed were obtained (team-lead authorisation
  and a tax-compliance review *before* landing, on IHTA 1984 s5(1) grounds — tenants in
  common hold distinct undivided shares). Projected properties fall £4,550,296.97 →
  £4,037,301.71 and **a user's projected Inheritance Tax liability falls by £205,198.11.**
  Nobody has seen that on a screen. A quarter of a million pounds moving on a headline
  figure is exactly the change that should not reach a user unwitnessed.
- **W-0335** — acceptance 1–3 are met on their own terms and both resolver branches were
  read back with real data (David 79.8 months / £99,750 from `expenditure_profile`; Sarah
  25.33 / £31,030 from `user_monthly`). **Gated on an undeclared gap:** `/m` re-derives this
  client-side, which is the same class of defect W-0464 exists to remove under CSJ's
  standing rule that **`/m` must never work anything out**. The item does not declare it.

---

## BATCH E — W-0040, W-0189, W-0241, W-0391 … W-0452 (23 items)

### CERTIFIED (11)

**W-0241 · W-0391 · W-0393 · W-0397 · W-0412 · W-0421 · W-0422 · W-0423 · W-0441 ·
W-0444**

**W-0241 — defined benefit pensions counted at £0 via a phantom column. The best-executed
item on this board, and I read it in full myself rather than through an agent.**
Every element the constitution asks for is present and several it does not:

- The phantom reader deleted, and the claim that others existed **checked rather than
  assumed** — the surviving `transfer_value` matches in `ISAAllowanceOptimizer`,
  `BedAndISACalculator` and `BedAndISATransfers.vue` are an unrelated Bed-and-ISA array
  key, correctly left alone.
- **The CSJ ruling implemented, not improved on**: no column, no migration, no
  capitalisation multiple.
- **The clause that mattered measured both ways** — net worth £1,489,500 / £739,280 and
  both retirement cards byte-identical before and after. *"If a number moves, the change
  is wrong"*, and none did.
- **The tests deliberately avoid the £0**, naming the reason: it reads the same under the
  bug and under the correct exclusion (`tests/CLAUDE.md` §4, collision variant). They
  assert the disclosure flag, a Defined Contribution figure that *moves*, and a case where
  adding a Defined Benefit scheme flips `has_db_pensions` false → true while net worth
  stays put. One test names `805000.0` explicitly as the number that must not come back.
- **The disclosure proven to come from the backend**: the sentence has zero occurrences in
  the rebuilt `/m` bundle yet renders on screen — the bundle physically cannot supply it.
- **Not clipped, measured**: `scrollHeight === clientHeight` at 390×844.
- **The negative case asserted on element counts, not on text** — David's `.mnw-note` and
  `.mnwc-disclosure` counts are 0.
- **It corrects itself in writing.** An earlier claim that the ×20 capitalisation was live
  on web too is withdrawn: `NetWorthOverview.vue` is dead code. The defect and the fix are
  unchanged; the surface count was overstated, and the retraction is recorded rather than
  quietly dropped.

The one open thread — two Swift-only gaps — is filed as **W-0311 with the exact change
written out**, and `Codable` ignores unlisted keys so nothing breaks meanwhile.

- **W-0412** carries the single most falsification-resistant artefact in the batch: a
  network capture showing `221. [PUT] /api/user/profile/expenditure => [200]` **and the
  absence of `PUT /api/users/17/expenditure`**, with the audit row read back —
  `#1465 actor=16 target=17`, the exact mirror of the pre-fix `#1376`. Proving a request
  *did not happen* is much harder than proving one did. It also routed Fyn's writer, found
  W-0202's open criterion, and **reverted** rather than shipping past a parked decision.
- **W-0421** used the third party's money as the discriminator: Manchester renders "Your
  share £118,000 of £295,000 · Your mortgage share £48,000" and **£177,000 / £72,000 appear
  nowhere** on screen, in prose, or in the exported document — with the export verified by
  a real click, not by inspecting the generator.
- **W-0441** verified the tabs were present **before any holdings existed**, which is the
  discriminating order, then entered three persona holdings through the interface and read
  them back as rows 73/74/75 with units, prices, dates and charges.
- **W-0397** reconciles arithmetically rather than merely agreeing:
  `99,750 + 172,500 + 887,750 = 1,160,000 = gross_estate`, with 989,500 identical across
  the mobile dashboard, the will page and the `/m` estate screen.
- **W-0444** — my agent could not establish this one and said so; I checked it myself.
  `use App\Models\DCPension;` is present at
  `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php:10`. The stated
  mutation (removing the import reddens exactly the five not-found cases and leaves the
  owner case green) is the right discrimination, and the suite is green under my own run.

### CANNOT CERTIFY (12)

**The charitable-rate cluster — W-0451 and W-0452 — needs flagging hardest.**
**Every one of the five acceptance criteria on each item is unticked**, neither has a
browser-verification section, and W-0452 carries **only the pre-fix measurement**
(`/plans/estate` "Current Charitable Rate: 4.2%" against `/estate` 0.8%, same session,
same user) with **no post-fix rendered read at all.** Both carry
`gate: tax-compliance-reviewer-cleared-2026-08-23` in frontmatter while their own
tax-compliance acceptance criterion sits unticked — **the frontmatter and the acceptance
list disagree about whether the gate was passed.** W-0451 also names a **fifth** copy of
the mechanism at `GiftingStrategy:227`, explicitly not fixed. The code changes are real
and I verified them; the item state does not support certification.

**W-0432 is stale rather than incomplete, and that is its own problem.**
**Seven acceptance criteria unticked — and my agent measured four of them as actually
done**, closed by W-0451's later work (the code comments are tagged `W-0451`). So the
board text is behind the code. There is also **no browser section in the item at all**, the
second tier across trust and cross-module services is unswept, and a third pass for
control-flow literals is outstanding. Severity was raised medium → high by the tax gate's
C4. **An item whose record understates its own progress is as unusable as one that
overstates it** — both mean the board cannot be trusted without re-reading the code.

**The mirror-will pair escalates rather than closes — W-0395 and W-0396.**
Both fixes are real and W-0395's evidence is good (Sarah's Executors read "David Jones ·
Barclays Wealth"; `wills.id 12` read back as `Sarah Jones → David Jones`; doc 5 / `wills.id
11` verified **unchanged**; dry run before force). But the author's conclusion is the
finding: **"W-0024's open GATE is LARGER than it was, not resolved."** There are now **two
production populations** — every pre-W-0024 mirror will, plus every post-fix mirror where
the partner has a middle name (W-0396) — and **neither has been run against production.**
`compliance-lead` is unticked on both. W-0396 cannot be browser-verified on this persona at
all, because nobody in it has a middle name.

**Awaiting `tax-compliance-reviewer` — 3.** `W-0394` (whether a name-substring indicator
list is a defensible fallback **now that it decides what is stored**, not merely what is
displayed — a materially higher bar, and `BequestForm.vue` still offers no way to say "this
is a charity") · `W-0431` (rounding of a displayed rate, and that no branch can render a
rate differing from the one applied) · `W-0433` (four criteria unticked; "**Rendered page
unverified — browser handshake pending**").

**W-0399** — the rendered card was read as David with caches cleared by hand and a
screenshot named, and Sarah was **deliberately** not logged in with a test pinning why
(both figures are household properties). Blocked on an unticked `/m` line with no
counterpart, and on gate conditions routed onward (C2 → W-0433, C4 → W-0432). It records a
coupling worth keeping in view: **the pooled exemption is correct only because the model
never settles the first death.**

**W-0411** — good evidence on both surfaces (web: "2 goals are behind schedule" replacing
"All goals on track!", three healthy goals still On track; `/m`: "Goals on track — 3 of 5",
both overdue reading OVERDUE, bundle identity confirmed). Gated on the author's own
**"I COULD NOT TEST THIS"**: `GoalsOverviewCard.vue` on `/dashboard` renders **Locked** for
this user, so **its second copy of the banner has never been rendered by anyone.** This is
named check #2 in the cycle-4 tester brief and needs an account with the Goals module
unlocked.

**W-0401** — the before/after is real (Sarah 2 recommendations → 1, David unchanged) but it
was measured through `tinker` and a service call, not a browser interaction, on a surface
that renders recommendations to a user.

**W-0040 and W-0189** — no browser verification, by instruction. W-0040 additionally leaves
legacy rows stored at 100 on shared types unrepaired, deferred to the W-0043/W-0161 sweep
as CSJ's call.

---

## ADDENDUM — two items that arrived after my scan. Both CERTIFIED.

My inventory read `status:` from each file's first twelve lines. **W-0325** and **W-0327**
carry it lower, so neither appeared in the 149. Both are at `handoff`, both are mine, and
both are among the best-evidenced items on the board.

**W-0325 — every joint property update 500s (`PropertyController` missing
`use App\Models\User`). CERTIFIED.** All three criteria met and I checked each:

1. `use App\Models\User;` is present at `app/Http/Controllers/Api/PropertyController.php:15`.
2. **Browser-verified on the exact reproduction case** — property 9 as David (16), Full
   Property Value £850,000 → £862,500, **200 not 500**, `properties.current_value` read
   back `862500.00`, **and `joint_account_logs` gained row 3**. That log row is the
   load-bearing half: a 200 shows the request did not crash, the row shows
   `logJointPropertyUpdate()` **bound its `User` argument** — the exact operation that used
   to throw. Test data restored afterwards.
3. **The sweep is clean and counted** — every `.php` under `app/` outside `App\Models`
   scanned for a bare `User` type hint, nullable type, return type or static with no
   `App\Models\User` import: **zero files.**

This is also the clearest instance of the formatter trap in `tests/CLAUDE.md` §2 doing real
damage: the import was deleted while unreferenced, `php -l` passed, the file parsed, and
nothing failed until a user pressed Save.

**W-0327 — savings edit emits `save`, the page listened for `saved`. CERTIFIED, with one
correction to the item's own acceptance.**

The evidence is exemplary in a specific way: **the defect was reproduced in the browser
before anything was touched** (`/savings/account/27`, balance → £26,750, database still
`25000.00`, modal open, no request), then re-verified after
(`savings_accounts.current_balance = 26750.00`, modal closed, page shows £26,750, restored
to £25,000). Before *and* after, same account, same edit.

**What makes it worth reading is the second finding.** The event name was only the first of
three faults: `handleAccountSaved()` took no argument and made no request, and
`updateAccount` was not in the page's `mapActions`. The author's conclusion is the one that
matters — **renaming `@saved` to `@save` alone would have closed the modal and re-rendered
the unchanged account, losing the edit while looking as though it had saved. A worse
failure than the one being fixed.** A one-line fix that measures green and makes the bug
invisible is precisely what this board should be watching for.

Rule 20 was honoured by mirroring the correct sibling rather than inventing a shape —
including its preview-mode branch, where the API returns a fake success and reloading would
show the old value. Rule 19 answered properly: `resources/mobile/` has no savings edit
surface at all, so there is **"nothing to bring to parity, rather than parity skipped."**

**The correction.** Acceptance 1 requires *"passing `:is-editing` as `AccountDetails`
does"*, and `SavingsAccountDetail.vue:181-186` does **not** pass it. I checked whether that
matters: it does not, and the criterion is wrong.
**`SaveAccountModal` has no `isEditing` prop — it is a computed:**

```js
isEditing() {
  return !!this.account;
}
```
`resources/js/components/Savings/SaveAccountModal.vue:650`

The page passes `:account`, so the modal is in edit mode. The item's Intent hedged on this
correctly ("the modal *may* not be in edit mode either") and the answer is that it is.

**Two things follow that belong on the board.** `AccountDetails.vue:144` passes
`:is-editing="isEditingAccount"` into a component with no such prop — **a dead binding**,
and the source of the false premise in this item's acceptance. It is harmless today and
actively misleading to the next reader, who will reasonably infer the prop exists. Worth a
one-line tidy, raised here rather than fixed, because I am the gate and not a fixer.

---

# THE VERDICT

**151 items judged** — the 149 at `handoff`, plus W-0325 and W-0327 found late.

| Verdict | Count | `status:` now |
|---|---:|---|
| **CERTIFIED** | **45** | `done` |
| **REJECTED** | **5** | `queued` |
| **CANNOT CERTIFY** | **101** | `gated` |

Every item carries a `certification:` line in its frontmatter pointing here, so the board
is greppable: `grep -l '^certification: REJECTED' workforce/ops/board/W-*.md`.

## What the gate stopped — the answer to "has this gate ever blocked anything"

**5 rejections and 101 blocks: 106 of 151 stopped.** That is not a gate performing; it is
a gate meeting a board that was never assembled for one.

### The five rejections, in the order I would act on them

1. **W-0349 — the spouse-invite endpoint is still an account-enumeration oracle**, and
   **its test asserts the behaviour its own title forbids** — it asserts the distinguishing
   key is *present*. A test that pins the defect will hold it in place through every future
   refactor. Two of four outcomes collapsed; registered-vs-unregistered still differs.
2. **W-0190 — joint expenditure still splits 100/0 through the only door `/m` has.**
   The service fix and migration are real; `CoordinatingAgent::handleSetExpenditure()` was
   not fixed, and on `/m` Fyn is the sole editing path. Rule 19 is not satisfied by a
   backend fix the surface cannot reach.
3. **W-0138 — the author wrote "Do not close."** Faults 2 and 3 untouched, acceptance 5 not
   done. `status: handoff` was simply the wrong field to set.
4. **W-0012 — the mortgage wizard still drops the Rate Fix End Date in the browser.**
   Backend fixed, `PropertyList.vue` untouched since before the work began, **and the test
   passes because it POSTs the key straight to the API.** The test and the browser use
   different doors.
5. **W-0008 — the adviser fee can be entered and was never shown to reach the projection**
   it is entered for, which is the item's entire headline.

### The blocks that are not merely "no screenshot"

Most of the 101 are missing browser evidence. **These are not:**

- **W-0347 (CRITICAL, forged consent)** — excellent fix, `gate: compliance-lead` open. Not
  mine to clear.
- **W-0463 / W-0464 / W-0465** — this branch's two commits change **Inheritance Tax
  arithmetic** and have not been through the tax gate they are explicitly gated on.
  **Three rounds of review have produced three crops of defects, each found inside the fix
  for the last** — including R1, **£110,000 of invented tax**. A fourth round has not
  happened.
- **W-0344 — three of 53 `spouse_id` consumers use the reciprocity rule.** W-0350 untouched.
- **W-0272 — a linked spouse is still assessed as childless in eight consumers**, including
  the Fyn prompt builder.
- **W-0322 / W-0257** — the client stopped sending the destructive payload; **the endpoint
  still accepts it**, and `/m`, native and Fyn all post to it.
- **W-0244 / W-0264 / W-0336 / W-0342 / W-0335** — Rule 20 residuals: two mechanisms
  survive, in one case **agreeing only by coincidence of one persona's data**.
- **W-0333 — a projected Inheritance Tax liability falls £205,198.11 and nobody has seen it
  on a screen.**
- **W-0173 — a migration written and left unapplied**, with the author's own warning that
  without it the item reads as unfixed. State unknown is not a pass.
- **W-0395 / W-0396** — the mirror-will gate is **larger than before**: two production
  populations, neither run against production.
- **W-0451 / W-0452 / W-0432 / W-0433** — the charitable-rate cluster: ten of ten acceptance
  criteria unticked across the first two, no browser section in three of the four, and
  frontmatter claiming a tax gate their own acceptance lists say is open.

## The three findings I would put in front of CSJ before any of the above

**1. The evidence pack does not exist as an artefact.** Zero `evidence/` directories. What
exists is prose in board items and branch documents, **written by the agent that wrote the
code** — which `08-process.md` §2.4 defines as not evidence. The gate did not fail; **it
was never given the thing it gates on.** I supplied the one part nobody had: a full-suite
run and a Pint run, by someone who wrote none of the code.

**2. Six of the seven code-quality rows have never been run on any of the 151** —
`security-reviewer` (on a board containing four cross-account disclosure items),
`tax-compliance-reviewer` on most tax changes, `design-lint.sh`, `tech-debt-session`,
`tax-hardcode-check`, `m-parity-check`.

**3. `status: handoff` and the acceptance checkbox both carry no information.**
94 of 149 items had no ticked box, and several of those were completely and correctly fixed
— W-0006, W-0137 and W-0188 among them, which I verified in the code myself. W-0432's board
text **understates** its own progress by four criteria. W-0228 **ticks a browser criterion
with no browser behind it**. The field records the moment it was written and nothing since.
**Read the code, not the board** — which is the opposite of what a board is for.

## What I could not do, stated as a gap rather than disguised

**I did not re-drive a single journey in a browser myself.** 151 items, one shared
Playwright tab that was not mine to seize, and a branch that is not deployed to csjones
(dev sits at `5c556e252`, two commits behind this work), so `/m` could not be checked
where `/m` is checked.

**Every "browser evidence present" verdict above means the item records a specific,
falsifiable interaction — not that I watched it.** Where that evidence is a database row
before and after, an HTTP status, a network capture proving a request did *not* happen, or
a grep of the built bundle for a string that can only exist if the change shipped, it is
hard to fabricate and I have leaned on it. Where it was "verified on both accounts" with
nothing behind it, I said so.

**That is the pack's remaining hole. Closing it is a tester run against a deployed branch,
not more desk work.**

## Recommendation

**Do not merge this branch to `dev` on this pack.** Two specific, cheap actions unblock
most of the value:

1. **Run `tax-compliance-reviewer` on `88494e0fd` and `8f09eaddc`.** On this branch's own
   track record that review has found HIGH regressions in every previous round.
2. **Run `compliance-lead` on W-0347, W-0466, W-0467.** Three copy-and-consent items, all
   settled by CSJ, all waiting on one review.

Then deploy the branch to csjones and give a tester the browser list: W-0137/W-0188 (the
expanded age-84 table on both accounts), W-0237 (`/m` liabilities must read £170,500),
W-0238 (Sarah on `/m` after the rebuild), W-0411 (an account with Goals unlocked), and
W-0469 (the new `/m` estate handoff card).
