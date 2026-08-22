# Coordinator handover — persona run, 2026-08-21 (power-down)

**Written under a power-loss warning.** Read this for how the run got here.

> **For current state, read `RUN-STATE-2026-08-21.md` beside this file first.** It is a
> 20:19 snapshot of all 103 board items, the eleven branch documents, the decisions taken
> and what CSJ owes an answer on. **This document is the older record**, and §3 and §9
> carry superseded-markers — act on those before acting on what they annotate.

---

## 1. What this run is

Persona `peak_earners` (David & Sarah **Jones**, renamed from Mitchell; third-party
co-owner **Mike Barrett**, renamed from Mike Jones). Contract:
`tests/Persona/peak_earners.md`, built from the PDF in the same folder.

**Protocol (CSJ, agreed):** three passes — A: web forms, B: `/m` via Fyn, C: iOS via
Fyn — each entering the whole household, verifying on **all** surfaces from **both**
accounts, then tearing down. Web and `/m` go green **locally** first, then dev. iOS is
dev-only. Every check loops until green (Rule 14).

**Where the run actually got to:** Pass A entry was **halted deliberately** to fix a
large defect batch first. No pass has been completed. The re-run playbook
(`PASS-PLAYBOOK.md`, 1,386 lines) is the instrument for restarting it.

---

## 2. Key artefacts

| Path | What |
|---|---|
| `tests/Persona/peak_earners.md` | The contract |
| `tests/Persona/20-08-2026_run/PASS-PLAYBOOK.md` | Re-run playbook: entry map, precomputed expected values, per-account matrix, regression check per defect, Pass B/C conversation scripts |
| `tests/Persona/20-08-2026_run/RUN-LOG.md` | Index of all reports; **leads with coverage caveats** |
| `tests/Persona/20-08-2026_run/reports/R-01…R-15` | Per-stage reports |
| `tests/Persona/20-08-2026_run/reports/R-14-handover.md` | **Tester's own handover** — browser contexts, frozen state, cleared false positives |
| `tests/Persona/20-08-2026_run/pass-a-web/` | ~40 screenshots (`VOID-` prefix = deliberately invalidated, do not trust) |
| `workforce/ops/board/W-00NN-*.md` | ~55 work items |
| `workforce/branches/fixes/F-0001…F-0004` | Per-agent branch documents (complete seeds for replacements) |
| `workforce/ops/reports/2026-08-21-*` | Compliance delta report, consent ruling, regime map proposal |
| `workforce/ops/FORMATS.md` | **Process rules added today** — ID blocks, claim-at-dispatch, migration ownership |

---

## 3. Agents at power-down

> **SUPERSEDED IN PART — read §13 and §14 before acting on this table.**
> - **`fix-batch-B`** is stale here: **§14 supersedes it** — its report arrived after
>   this section was written and the batch is complete.
> - **`fix-batch-D`** is stale here: **§13 supersedes it** — "UNKNOWN, never reported"
>   was true when written and is not true now. Its branch document is `F-0006`, and
>   W-0026 to W-0029 point at it.
> - **`fix-batch-E`** was later **respawned** (log, 16:25) after the original never
>   reported. `F-0004` still does not exist as at 2026-08-21 18:40.
> - Agents running as at 17:50 are in **§R4**, not here.
>
> The table is left as written: it is the record of what was known at power-down.

All were told to stop and write a handover. **On resume, do not assume any of them
completed that** — check each branch document's mtime before trusting it.

| Agent | State | Held |
|---|---|---|
| `fix-batch-A` | **RETIRED**, 12 items, `F-0002` final | — |
| `fix-batch-C` | Finishing **W-0036**, then standing down. `F-0001` current | W-0036 |
| `fix-batch-B` | Running: W-0046 backfill (authorised, may or may not have run), W-0053, `WillController::deleteBequest`, W-0037 | 4 items |
| `fix-batch-D` | **UNKNOWN — never reported.** Status check sent at power-down | W-0026/27/28/29 |
| `fix-batch-E` | Started **W-0035**, then W-0032 | W-0035, W-0032 |
| `design-palette-fix` | W-0045 done; on education-level acronym; authorised `app.css:323` | — |
| `compliance-perimeter` | On the W-0100 perimeter half | — |
| `persona-passA2` | Tester, **~715k** — closest to the Rule 22 buffer. On the Inheritance Tax cache check | — |

**Retired earlier:** `persona-passA` (original tester, handed over at ~890k → `R-08`).

---

## 4. Decisions PENDING CSJ — nothing else waits on me

1. **The cookie wall.** Can registration be conditioned on consent to analytics and
   affiliate tracking? Only removing the condition addresses "freely given" (Art 7(4));
   better copy does not. Compliance drafted for the unconditioned state.
2. **Premium subscribers in the no-partner class lose a paid capability on release**
   (W-0019). Must be decided **before** release; afterwards it is remediation, not
   disclosure.
3. **The risk-module colour spectrum** (W-0048) — 276 occurrences, a deliberate
   five-step green→teal→blue→red scale, and the palette has no five-step sequential
   scale. Azlan's call. Blocks reducing the Tailwind safelist.
4. **`fynlaDesignGuide.md` contains an unbuildable clause** — the Info badge specifies
   `text-light-blue-700`; `light-blue` is defined at 100 and 500 only, so `@apply` is a
   build error, and the nearest valid pair is 2.9:1, below the AA floor the guide
   itself mandates.
5. **The §1 regime map** (`workforce/ops/reports/2026-08-21-perimeter-regime-map-proposal.md`)
   — adopt, and where it lives. Trunk unamended.
6. **Persona file self-contradictions** — expenditure headline £2,500 vs categories
   summing £2,450; net-worth range only fits excluding pensions. In the source PDF, not
   the transcription.
7. **W-0018 Spec §5.2** — no such spec exists in the repo; it is the only route by
   which `users.tier` could be meant to win.

**ANSWERED, do not re-ask:** production mirror-will count (**zero real customers** —
all 4 were preview personas; exposure closed); mirror-wills-only + spouse-who-won't-
engage gets the solicitor message; `scheme_status` gets a column; W-0040 (100/0 split)
is nonsensical — that is individual ownership; backfill and design compliance are
defects not decisions.

---

## 5. Environment state

- **Local:** Laravel on `:8000`, Vite on `:5173`, `public/hot` regenerated 21:31 on 20 Aug.
- **`/m` bundle:** rebuilt, serving `main-Df3Ab1_w.js`. **No HMR — rebuild
  (`npm run build:mobile`) after ANY `resources/mobile/` change or you test history.**
  Build artefacts are the coordinator's; agents ask.
- **Test databases:** `laravel_testing_a/_b/_c/_d` and `fynla_testing_batcha`, one per
  agent. `phpunit.xml:46` pins to a shared DB; the shell override wins (no `force`).
- **Playwright MCP:** fixed (corrupted npx cache, `ENOTEMPTY` on
  `~/.npm/_npx/9833c18b2d85bc59`); pinned to `--browser chrome`, headed.
- **Live test users:** David **16** / Sarah **17** (linked, premium, the fix batches'
  reproduction data — DO NOT DELETE). Priya **20** (premium), spouse **30**, Tomas
  **31** (premium, 2 life events). `db_pensions.id 4` deliberately unpatched as an
  acceptance fixture.
- **Teardown list, none actioned yet:** users 20, 30, 31; `family_members` 25/26/46/47;
  both `SpousePermission` rows. **20 and 30 are the only live reproduction of W-0051** —
  keep until its fix lands.
- **Production:** untouched except two read-only queries (will count, `awin.enabled`).

---

## 6. Live production exposures — not fixed, on `origin/main`

1. **`awin.enabled` is TRUE.** `CaptureAwcCookie` is in the global middleware stack with
   **no consent check**, setting a 365-day **HttpOnly** `awc` cookie on **every
   visitor**. `declineCookies()` is structurally incapable of clearing it. → W-0049.
2. **Consent cannot be demonstrated (Art 7(1))** — it exists only as a `localStorage`
   string, no server record, since 2026-04-07. **Not repairable retrospectively.**
3. **Google Analytics falls back to the production measurement ID** when `VITE_GA_ID`
   is unset, so local and test runs report into the live property. → W-0047.
4. **Production defaults married users to a one-sided will**
   (`origin/main:WillBuilderIntroStep.vue:114`), and `generateMirrorWill()` still copies
   executors verbatim. **Zero real customers affected** — but that is timing, not a
   control.

---

## 7. First actions on resume

1. **Read this file, then `RUN-LOG.md`, then `R-14-handover.md`.**
2. **Check each agent's branch document mtime** — establish who finished their handover
   and who died mid-write. Anything unfinished, reconstruct from the working tree
   (`git status`, file mtimes), **not from the board** — the board lags.
3. **`git status`** — the tree carries a large volume of uncommitted work from six
   agents. Nothing was committed, no PR was opened, nothing was deployed.
4. **Re-check `fix-batch-D`** — it never reported and its four items' state is unknown.
   Its files are the Protection and Goals surfaces.
5. **Respawn agents from their branch documents**, not from scratch. `F-0001`–`F-0004`
   are written as complete seeds.
6. **Nothing was deployed and no PR was opened.** The dev leg has not started.

---

## 8. Process rules established today (in `FORMATS.md` and `CLAUDE.md`)

- **CLAUDE.md Rule 21** — invoking a tester makes the main inference the run's
  coordinator; the tester idles only for a CSJ decision or a finished green run.
- **CLAUDE.md Rule 22** — 900k context buffer; hand over rather than hit the ceiling.
  It fired successfully once (the original tester at ~890k).
- **ID blocks, not next-free** — two collisions proved scanning is not atomic.
- **Claim at dispatch, not after starting** — a near-miss put two agents one edit apart
  in the same file.
- **`migrate --path=`, never bare `migrate`** on a shared dev database.
- **Trust the working tree over the board when they disagree.**
- **Fix agents do not browser-verify their own work** — the tester closes Rule 14's
  loop independently.


---

## 9. Tree snapshot at power-down (evidence, since agents may not have finished handovers)

> **SUPERSEDED — see §13, the §R sections, and
> `workforce/ops/reports/2026-08-21-consistency-sweep.md`.**
> - The **548 paths** figure is a power-down snapshot. The sweep measured **681** at
>   18:13 on 2026-08-21 — and note that figure needs `git status --porcelain -uall`;
>   the default output collapses new directories and undercounts.
> - **"Branch documents that did NOT exist"** is stale in both entries. `F-0005` is
>   design-lead's document, not batch D's; batch D's is **`F-0006`**. Nine branch
>   documents now exist (`F-0001` to `F-0003`, `F-0005` to `F-0010`) and every one has
>   an inbound board reference. **`F-0004` (batch E) is the only one still absent.**
> - §13 already resolves the `fix-batch-D` question this section leaves open.
>
> Left as written: it is the evidence that was available at power-down, and the
> instruction not to read absence as "no work done" was correct and still is.

**548 changed/untracked paths. Nothing committed, no PR, nothing deployed.**

**Branch documents that EXIST** (trust these): `F-0001` (batch C, 13:54), `F-0002`
(batch A, 13:35), `F-0003` (batch B, 13:45).
**Branch documents that did NOT exist at power-down:** `F-0004` (batch E), `F-0005`
(batch D). Both agents were told to write them in the final seconds — **check whether
they landed before trusting their absence as "no work done".**

**`fix-batch-D` HAD been working, despite never reporting.** Evidence in the tree:
`app/Models/Concerns/RecordsPolicyDates.php` (new, untracked) — a concern for recording
policy dates, which is W-0026's territory (policy end date silently discarded).
**Do not assume batch D did nothing; assume its work is unreported, not absent.**

**`fix-batch-E` HAD started W-0035** despite reporting only ~65k of read-only prior
art: `app/Http/Requests/Retirement/UpdateRetirementGoalsRequest.php` (new, untracked) —
the request class for the missing Target Retirement Income endpoint.

**Other new files worth knowing about on resume:**

- `app/Constants/ProfileEnums.php` — batch C's enforcement chain (the single source the
  parity tests bind web and `/m` to).
- `app/Console/Commands/BackfillWillBequests.php` — batch B's W-0046 backfill command.
  **Establish whether `--force` actually ran** before re-running it.
- `.claude/agents/persona-tester.md` — the tester agent definition written this session.
- Two pre-existing stashes on `dev` — **not from this run**, do not assume they are.

**On resume, reconstruct from the tree first, then reconcile against the board.** The
board lags by design; the tree is what actually happened.


---

## 10. LATE FINDING — a fifth live production exposure (compliance, filed at power-down)

**The application tells users their Lasting Power of Attorney is "Compliant", in green,
on production, and has done for five months.**

- `LpaComplianceService.php:49` can return `'compliant'`.
- `LpaComplianceChecklist.vue:97` renders **"Compliant"**; `:88` renders it in the
  **success colour**.
- On production since `1a3d17e99`, **2026-03-16**.

**This is independent of W-0100's generator audit and must not wait on it.** The badge
is an overclaim *even if the generator is flawless*, on two grounds:

1. **Perimeter §7.3 already forbids exactly this — of the compliance agent.** It may
   report "no issues found within my competence", may never "approve anything as
   legally compliant", and exists to prevent "a confident-looking compliance sign-off
   that nobody questions… it stops a human from looking." **That paragraph describes
   the service exactly.**
2. **The object assessed is not the instrument.** The checks run on stored form data,
   while validity turns on actual capacity at signing, whether the statutory
   certificate was genuinely given, the manner of execution, and whether the Public
   Guardian has registered it. **A perfect checker would still not be entitled to the
   word.**

**The trunk recommendation compliance would take first, of its four:** §7.3 contains
exactly the right rule and applies it to the wrong half of the system. **The gap is
scope, not content** — extend it so no Fynla surface tells a user that anything they
hold is compliant, approved, valid or sufficient. As it stands the trunk forbids an
agent from saying "compliant" while the application says it in green.

**Legal grounding established rather than assumed:** LSA 2007 Sch 2 para 5(3) excludes
**(a)** "a will or other testamentary instrument" and **(c)** "a letter or power of
attorney" — one line apart, same sub-paragraph. So the will analysis carries across for
the same reason, not by resemblance. **But "not reserved" is not "permitted"** and the
rest of the question differs.

**Two ways an LPA is NOT like a will, both cutting against us:**

- **MCA 2005 Sch 1 para 1 requires a statutorily prescribed form.** A will has none. An
  instrument departing from it is not a defective LPA — **it is not an LPA**. The
  failure is binary and surfaces **at registration, possibly after the donor has lost
  capacity.**
- **Sch 1 para 2(6): "The certificate may not be given by a person appointed as
  donee."** That is the **W-0024 shape — a party in a role they cannot hold — written
  into the statute.** Test it first.

**Trap for whoever does W-0100 acceptance 1–3:** MCA Sch 1 carries pending amendments
from the **Powers of Attorney Act 2023, NOT in force as at 2026-08-20**. Same shape as
W-0050's stale PECR citation — **check commencement before relying on current text.**

**Unknown, deliberately not queried:** how many real users hold an LPA on production.
One read-only count answers it, as the mirror-will count did.

**Compliance's standing position, recorded:** none of its output is an approval. It has
**not** ruled the cookie consent invalid, and has **not** determined that generating or
assessing an LPA is permissible.

**W-0100's perimeter half (acceptance 5) is COMPLETE — do not have anyone redo it.**
Acceptance 1–4 (reading the renderer, auditing the checks) are untouched and are
build-lead's.

Compliance's own power-loss note:
`workforce/ops/reports/2026-08-21-compliance-lead-power-loss-note.md`. All five of its
artefacts verified present on disk.


---

## 11. LATE FINDING — the "one place" for education copy did not exist (design, filed at power-down)

**W-0080 done, but the instruction it was given was wrong and the agent said so.** I
told it to "change the label in the one place that feeds all three surfaces". **There
was no such place. There were FOUR renderers of this copy:**

1. `resources/js/constants/profileOptions.js` — desktop
2. `resources/mobile/constants/profileOptions.js` — `/m`
3. **`ComprehensiveProtectionPlanService.php:206` — a private `match` expression nothing
   compared against**
4. — and **no backend home for the copy at all**; `ProfileEnums` held **values only**,
   never labels

**The parity spec bound 1 and 2. Nothing bound 3.** It would have gone on rendering the
old label into protection plan output after both selects were corrected, **and no test
would have gone red.** Same disease as W-0031, one layer up: **values were pinned, copy
was not.**

Fixed by creating the single home: `ProfileEnums::EDUCATION_LEVEL_LABELS`, now read by
the protection service and mirrored — **label by label, not just value by value** — by
both frontends.

**It proved the new spec can fail**, rather than trusting a green run: injected a
deliberate divergence, three tests went red naming the offender, restored, re-ran green.
The acronym test is **shape-based** (`/\b[A-Z]{2,}\b/`), not a denylist, **so a newly
added acronym is caught too.**

### Two things for CSJ

- **`a_level`: "A-Levels/Vocational" → "Advanced Level or Vocational".** The agent flags
  this as the borderline call: "A" is short for "Advanced", so Rule 9 catches it on the
  same reading that caught "RPT" — but "A-Level" is arguably a proper name.
  **Independently revertible from the `secondary` fix if CSJ or Azlan disagree.**
- **House style, raised not acted on:** health and smoking labels are sentence case,
  education is Title Case — **education is the outlier in its own file.** Not a Rule 9
  matter, touches four non-defective labels, and would have invalidated the tester's
  playbook strings mid-run for zero rule gain. Needs design + product agreement.

### One gap

**`tests/Unit/Database/ProfileEnumColumnsTest.php` was NOT run** — `laravel_testing_e`
did not exist. **I have now created it.** Reasoning says the test is unaffected (a
constant was added, no value list touched), but that is reasoning, not a green run, and
the agent correctly refused to mark it done. **Run it on resume.**

Design's other board items: **W-0081** (`/m` stylesheet hardcodes nine non-palette hex
values, including `--neutral-400`/`--neutral-600` which **invent shades of a palette
token** — they look like Fynla tokens at every call site while being Tailwind greys),
and **W-0082** (the safelist root cause plus the full 916-occurrence ledger, with the
warning that **safelist entries must be removed LAST per cluster, not first**, or pages
break before they are migrated).


---

## 12. CORRECTIONS filed at the last moment — read these before acting on §11

**1. `app.css:323` DID NOT LAND. Clean slate.** `git status --porcelain` on
`resources/css/app.css` returns **no output** — zero bytes changed. My authorisation
and my stop order arrived in the same message batch, so there was no window between
them. `.badge-vct` / `.badge-eis` are still `bg-pink-100 text-pink-800`.

**Nobody needs to inspect, revert or reconcile that file**, and **the tester needs no
warning** — the Investment surfaces are unchanged. It remains authorised and unstarted.

**2. ⚠ `W-0082` DUPLICATES `W-0048`. DO NOT WORK BOTH.** Both cover the Tailwind
safelist root cause and the sweep ledger; they were written in parallel before the
messages crossed. **Fold one into the other and close the loser on resume.**

Three things from W-0082 must survive whichever wins:
- `app.css:323` is the **highest-leverage single line** — one line, every VCT/EIS badge
  app-wide.
- The Risk module's **276 occurrences are Azlan's design decision, not a token swap** —
  the palette has no five-step sequential ramp.
- **Safelist entries must be removed LAST per cluster, not first**, or pages break
  before they are migrated.

**3. `laravel_testing_e` NOW EXISTS — I created it after design reported it missing.**
Design's note says it was never created; that crossed with my creating it.
**`tests/Unit/Database/ProfileEnumColumnsTest.php` still never ran** — that gap is real
and stands. Run it on resume; the database is waiting.

**Design's branch document is `workforce/branches/fixes/F-0005-design-lead-palette-and-copy.md`**
(note: this takes the F-0005 number I had earmarked for batch D in §9 — check both
before assuming which is which). Its education work was **complete** before the stop:
six files, 25 vitest tests green, specs proven able to fail. Nothing mid-edit.
Position at stop ~145k — the buffer was never a factor.


---

## 13. RESOLVED — batch D's state is no longer unknown

**`workforce/branches/fixes/F-0006-batch-d-protection-goals.md` EXISTS** — 8.3k,
written 14:27, i.e. it landed after the stop order. **§3 and §9's "batch D state
unknown" is superseded: read that document.**

**⚠ TWO DIFFERENT DOCUMENTS SHARE THE F-0005 NUMBER**, written in parallel by agents
who could not see each other:

- `F-0006-batch-d-protection-goals.md` — batch D (Protection, Goals, `/m` life events)
- `F-0005-design-lead-palette-and-copy.md` — design-lead (palette, education copy)

**Both are valid. Renumber one on resume** — the filenames disambiguate them, so
nothing is lost, but do not let a later reader assume one supersedes the other.

**Batch D had done substantial work**, confirmed by file mtimes rather than by report:

- `app/Models/Concerns/RecordsPolicyDates.php` — **new concern**, W-0026's territory
  (policy end date validated, accepted, 201'd, then silently discarded on 4 of 5 policy
  types)
- **Five policy models touched**: `LifeInsurancePolicy`, `CriticalIllnessPolicy`,
  `IncomeProtectionPolicy`, `DisabilityPolicy`, `SicknessIllnessPolicy` — consistent
  with fixing the discard across **all** policy types rather than the one reported
- `StoreLifePolicyRequest`, `UpdateLifePolicyRequest`,
  `UpdateCriticalIllnessPolicyRequest` — W-0027 (single beneficiary, no joint-life flag)
- `PolicyFormModal.vue`, `PolicyDetail.vue` — the front end of both
- `StoreGoalRequest.php`, `GoalFormModal.vue`, `LifeEventForm.vue` — W-0029 (goals and
  life events cannot be dated today or earlier)

**All four of its items appear to have been worked.** None of it is verified, none of it
is browser-confirmed, and its own document is the authority on what is finished versus
half-applied — **read `F-0006-batch-d-protection-goals.md` before touching any
Protection or Goals file.**

Note `PolicyFormModal.test.js` was one of the files that went red under parallel-load
vitest timeouts earlier (398 changed lines). **That red was contention, not a code
failure** — it passed in isolation. Do not read it as a defect on resume.


---

## 14. BATCH B COMPLETED — report arrived after §3 was written. §3 IS STALE FOR BATCH B.

**§3 lists batch B as "running, 4 items". That is wrong. All four are DONE.** 633 passed,
1 skipped, 0 failures, Pint clean. Board items at `handoff` → `quality-lead`. `F-0003`
covers all of it. **Do not re-dispatch any of these.**

### W-0046 — the backfill RAN, `--force`, all six

```
Backfilled 6 wills -> 6 bequest rows.
| 24 | Sarah Mitchell | £10,000 -> £20,000 | 40% -> 40% | REVIEW |
| 17 | Sarah Jones    | £0      -> £10,000 | 40% -> 40% |        |
| 16 | David Jones    | £0      -> £10,000 | 40% -> 40% |        |
No estate changed its Inheritance Tax rate.
```

**Better than expected: Sarah Jones got British Heart Foundation, not Cancer Research
UK** — her will document had already been corrected during the run, so David → Cancer
Research UK and Sarah → British Heart Foundation now match `peak_earners.md` exactly.

**Idempotency proven against real data:** second `--force` → 18 bequests before and
after, 6 document-sourced, **12 hand-made untouched**. Sarah Mitchell's `REVIEW` row
left standing as directed.

### W-0053 — fixed, and MY FRAMING WAS WRONG

**A route back does exist.** `WillPlanning.vue:97-101` renders **"View Will"** whenever
`will_document_id` is set, opening the builder at Review. The tester's conclusion was
reasonable — **Edit genuinely does not reopen the wizard** — but "View Will" does. So
this was **one condition, not a new surface.**

`WillBuilderReviewStep.vue:81` carried `!isComplete`, hiding Generate the instant the
will completed; and `mirrorData` is only ever set by clicking Generate in that session,
never loaded from the server, so a reload could not know a counterpart existed. Fixed
three ways: the button consults `mirrorGenerated()` so it survives reloads and hides
once the pair exists; `generateMirrorWill()` returns the existing counterpart rather
than creating a second; and `validateDocument()` warns before completion.

- **My question 1 — the stranded row is rescued through the UI, no migration needed.**
  Verified against the real `will_documents.14`.
- **My question 2 — NO, and it needs its own item.** `generateMirror()` scopes to
  `where('user_id', $request->user()->id)`, so **the pair is only ever creatable by the
  first testator.** The spouse has no route. Making it two-sided means one account
  writing a will document into another — **a `SpousePermission` decision**, correctly
  not added quietly inside this fix. **NOT YET RAISED — raise it on resume.**

### W-0041 second instance — `deleteBequest` fixed

200 + `{success, message}`. No joint/cascade branch here, but the will-document-linked
row was covered anyway. **Sweep of Estate now returns only `Admin/DocumentArticleController.php:73`,
which Batch A already cleared.**

**Reported not fixed:** deleting a bequest carrying a `will_document_id` removes the
row, but **re-completing that will recreates it from the document.** Arguably correct —
the document is the source of truth — but surprising from the Estate screen. Not raised.

### W-0037 — F9 folded in, cross-referenced from W-0020

The substantive addition: **`SaveWillDocumentRequest.php:49-54` is WHY the will-builder
half cannot be fixed in `syncBequests()` alone** — the gift shape has no charity field,
so the sync *physically cannot* set `beneficiary_type` however it is written. Same
reason priority cannot round-trip. **All four hops or nothing.**

### ⚠ ENVIRONMENT EVENT — something briefly wrote PROSE into `.env`

One regression run died with **`Failed to parse dotenv file`**. The file briefly
contained a sentence about losing power and winding down the agent. **Transient** —
`.env` was clean minutes later and `php artisan --version` worked. Batch B did not touch
it and correctly treated the text as data, not as an instruction (charter §3).

**CLOSED BY CSJ, 2026-08-21: do not investigate this. The current `.env` is fine.**
Treat a mid-run "environment file is invalid" as a transient environment event, re-run,
and move on. **Do not chase it as a code bug and do not open an item for it.**

**Standing guidance from CSJ on `.env` generally:** if you need a key or API access,
**check `.env`** — that is what it is there for. Never edit it (Rule: never touch `.env`
or DB rows to work around a bug), but reading it for credentials is expected and normal.

### Also recorded by batch B

`pgrep -f "vendor/bin/pest"` before starting a run — **the per-batch database fixes
contention BETWEEN agents, not WITHIN one**, and the failure signature is identical
(0 assertions). It lost time to this twice.

**Rule 14's loop is NOT closed by batch B on any item.** Local DB correct, production
untouched, David (16) and Sarah (17) changed only by the authorised backfill.


---

# RESUMED — 2026-08-21 17:11 BST. Everything above §14 is the power-down state; this
# section is what is true now. Where they disagree, this wins.

## R1. Verified on resume

- **Servers were down** (Laravel, Vite, `public/hot` gone). Restarted: Laravel `:8000`,
  Vite `:5173`, `public/hot` present. `curl localhost:8000` → 200.
- **All branch documents landed.** `F-0001` (batch C), `F-0002` (batch A), `F-0003`
  (batch B), `F-0005` (design), and batch D's. §9's "did not exist" list is fully
  superseded — **except `F-0004`, which still does not exist**: `fix-batch-E` never wrote
  one. That was the only genuine unknown and it is now assigned.
- **The duplicate F-0005 collision is resolved.** Batch D's document is renumbered
  **`F-0006-batch-d-protection-goals.md`**; the design-lead document keeps `F-0005`. The
  one cross-reference was updated.
- **`tests/Unit/Database/ProfileEnumColumnsTest.php` — the gap §11 named is CLOSED.**
  4 passed, 7 assertions on `laravel_testing_e`.
- **The three unpushed commits on `dev` are docs-only** (20 Aug evening and 08:20 today).
  "Nothing committed" still holds for every fix batch.
- **`fix-batch-E` had worked until 14:21**, later than any other agent — after the stop
  order. W-0035 is substantially built (`RetirementProfileStore`,
  `UpdateRetirementGoalsRequest`, a modified `RetirementController`, `RetirementGoalsTest`,
  a Vue spec). **W-0032 was never started** (`status: queued`, no migration).

## R2. The consolidation gate — run, and it is nearly green

Full suite over the entire uncommitted tree, `laravel_testing_a`:
**7,156 passed · 4 failed · 30 skipped · 125,507 assertions · 1,887s.**
Log: `scratchpad/consolidation-suite.log`. All four failures are assigned to `fix-batch-J`
and are the gate before anything commits. Root causes already established:

1. **& 3.** `TrialSchemaRemovalTest:91` and `AppleTransactionSubmissionApiTest:546` — the
   PR #710 guard tests, both naming the **same four leaked users** (ids 3501–3504,
   `created_at 17:24:28`, confirmed still present in `laravel_testing_a` after the run, so
   a real intra-run leak, not residue). Only four files in the suite commit or migrate;
   the offender is one of them. **The guards are working — do not touch them.**
2. `InvestmentModuleTest:188` — **a real new defect, not a stale test.**
   `HoldingValuation::reconcile()` (new today, W-0039) resolves `quantity` from the
   **existing record** before branching, so an inherited quantity silently overwrites an
   explicitly typed `current_value` (19.955704 × 450 = 8980.07 replacing a typed 45000).
   That is a user-typed figure validated, accepted, 200'd and discarded — the W-0026 class
   of defect. The units-are-authoritative direction is correct and must not be undone; the
   missing distinction is payload-supplied versus record-inherited.
3. `CaptureAccuracyGateTest:1017` — joint / tenants-in-common with a `joint_owner_id` and
   **no `ownership_percentage`** now returns 201 where the accuracy gate demands 422.
   Either a regression of W-0015's explicit-share rule, or a deliberate narrowing that
   must be re-pinned visibly. Batch J decides on evidence and says which.

## R3. CSJ decisions taken this session — all seven pending items are closed

| Was pending | CSJ ruling | Recorded in |
|---|---|---|
| Cookie wall | **Parked.** Accept cookies and proceed; not a run blocker. Art 7(4) waits for the functional board. | W-0050 |
| W-0019 premium withdrawal | **Proceed — there are no premium subscribers**, so no cohort to remediate and no disclosure. | W-0019 |
| Risk colour spectrum | **Keep as is. Entire palette workstream parked** until bugs are done. `app.css:323` authorisation **withdrawn**. | W-0048 (W-0082 folded in and closed; W-0081 parked) |
| Design-guide unbuildable clause | **Parked**, low priority. | W-0045 |
| The §1 regime map | **ADOPTED, and to be expanded properly.** Installation dispatched to `archivist`. | the proposal report |
| Persona self-contradictions | **`peak_earners.md` is the only source. The PDF is out of scope** — so they are not discrepancies. | `RUN-LOG.md`, `PASS-PLAYBOOK.md` |
| W-0018 Spec §5.2 | **Option (b)** — `users.tier` is a cache, entitlement is provider-truth only. §5.2 closed as unresolvable; **stop looking for it.** | W-0018 — **DONE, handoff** |

## R4. Agents running as at 17:50

| Agent | Items | Test DB |
|---|---|---|
| `fix-batch-E` | W-0035 (finish the predecessor's untracked work), W-0032, write `F-0004` | `_e` |
| `fix-batch-F` | W-0047, W-0049 | `_b` |
| `fix-batch-G` | W-0100 acceptance 1–4 + the **"Compliant" power-of-attorney badge** overclaim | `_c` |
| `fix-batch-I` | W-0051 at the mechanism level | `_d` |
| `fix-batch-J` | the four consolidation failures | `_a` |
| `regime-map` (archivist) | install + expand the §1 regime map | — |
| `persona-passA3` | batch B estate regression on Priya (20): Inheritance Tax cache, `deleteBequest`, gift→Bequest sync, W-0053, W-0037 | — |

**`laravel_testing_f` was created for the coordinator** so all five per-agent databases
stay free. ID blocks issued to prevent collisions: J **W-0121–130**, I **W-0111–120**,
G **W-0101–110**, tester **W-0131–140**.

## R5. Next, once batch J is green

Commit the tree onto a branch and PR to `dev` — **the single biggest fragility remains
that six agents' work has never been committed.** Then the second fix wave: W-0037,
W-0038, W-0040 (CSJ already ruled: a 100/0 split is individual ownership), W-0042, W-0043,
W-0044, W-0054. Then the full Pass A re-run on real pointer clicks per `PASS-PLAYBOOK.md`.
