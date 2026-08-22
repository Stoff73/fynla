# Weekly compliance scan — 2026-08-21

**Agent:** compliance-lead · **Cadence:** weekly, run regardless of whether anyone asked
**Scope:** the three surfaces I hard-block on — **tax services**, **AI prompt files**,
**public claims** — plus anything heading for publication.

> **Not an approval.** `05-perimeter.md` §7.3: two outcomes only — *no issues found within
> competence*, or *flagged with a reason and a dated source*. **Nothing below clears
> anything to ship.**

**Method:** scanned the working tree diff, not the last commit. The tree holds 554+
uncommitted paths from six agents and a tester, so a commit-based scan would have seen
almost nothing of today's work.

---

## Result in one line

**No block raised. Two findings, one on each blocking surface, both on changes that are
net improvements — so the findings are residual-risk notes, not objections.**

---

## 1. Tax services — **BLOCKING surface. Scanned. No block.**

**Changed, with content (renames excluded):**

- `app/Constants/TaxDefaults.php`
- `app/Services/TaxConfigService.php`
- `app/Services/Tax/IncomeDefinitionsService.php`

### What changed

Two new accessors centralising a duplicated inheritance-tax lookup:
`TaxConfigService::getCharitableReducedRate()` and `getCharitableThresholdPercent()`, with
a new `TaxDefaults::IHT_CHARITY_THRESHOLD = 0.10` behind the second. The docblock records
that `?? 0.36` was duplicated across **seven sites under two fallback conventions**
(`WillAnalysisService` ×2, `IHTCalculationService`, `EstateAgent`, `GiftingStrategy`,
`TaxSettingsController`, plus one reading `TaxDefaults` directly).

### Verified against the instrument — not taken on trust

**This is a blocking surface, so the figures were checked rather than assumed.**
Inheritance Tax Act 1984 Sch 1A, read `legislation.gov.uk` **2026-08-21**, latest available
(revised). Registered as row **A13**.

| Code | Instrument says | Match |
|---|---|---|
| `IHT_CHARITY_THRESHOLD = 0.10` | Sch 1A **para 2(2)**: *"the donated amount is at least **10%** of the baseline amount"* | ✅ |
| `IHT_CHARITABLE_RATE = 0.36` | Sch 1A **para 2(6)**: *"The lower rate of tax is **36%**"* | ✅ |

**Commencement:** Sch 1A was inserted by **Finance Act 2012 (c. 14) Sch 33 para 1, in force
17 July 2012**, and that is the only amendment applied to it.

### Two things recorded

1. **I verified against Sch 1A. I did NOT re-read s.7(1A)**, which
   `getCharitableReducedRate()`'s docblock cites for the 36% rate. Both citations are
   defensible — Sch 1A para 2(6) states the rate in terms — but **the s.7(1A) reference is
   unverified by me** and is recorded as such rather than silently blessed.
2. ⚠️ **One outstanding change against the Act: s.124L, to be inserted by 2026 c. 11
   Sch 12 para 6(1), not yet in force.** It does not bear on Sch 1A para 2 and does not
   affect either figure. **Recorded in register row A13 because anyone relying on another
   part of this Act must check it first** — this is the fourth commencement trap found in
   two days.

### The finding worth keeping

The docblock records that `inheritance_tax.charity_threshold_percent` **was seeded and
rendered in the admin Tax Settings screen as though it governed the calculation, while
nothing read it.** So an administrator could change that control and **nothing would
happen.**

**That is the shape of defect I care about most, and it is not primarily a tax bug.** It is
a control that misrepresents its own effect — the same family as the "Compliant" badge and
the will renderer's disclaimer sentence found today. **A control that appears to govern
something it does not is a claim, and it was a false one.** The change fixes it; recording
the shape so the next instance is recognised faster.

**No block.** The change is correct against the instrument, reduces seven sites to one, and
repairs an inert control. **This is not an approval** — it is *no issues found within my
competence*.

---

## 2. AI prompt files — **BLOCKING surface. Scanned. No block. One residual risk.**

**`app/Services/AI/Prompts/**` is unchanged.** The `prompts/` → `docs/reference/prompts/`
entries in the diff are the documentation reorganisation — **renames, no content change**.

**But `app/Services/AI/Fyn/FynContextAssembler.php` changed**, and that is a prompt file in
substance: `CLAUDE.md` states both Fyn states send `FynSystemPrompt::text()` **plus the
per-turn `FynContextAssembler::build()`**. **I treat it as inside the hard-block surface**
and say so, because the file path alone would have let it through.

### What changed, and it is right

A new `willStructureDirective()` injects a `<will_structure_policy>` block for married
users, **composing the refusal copy from `WillTypePolicy::REFUSAL_MARRIED` and
`REFUSAL_NO_MIRROR_PARTNER` rather than restating it.** The directive instructs the model to
reproduce the text unchanged and to add no exceptions, caveats or alternatives.

**This is Golden Rule 20 done properly** — W-0019's approved copy has one home and Fyn
quotes it rather than composing its own. It is exactly the pattern the LPA rulings assumed
and it is good to see it already in place.

### Residual risk — the failure mode is fail-open, and that should be explicit

The directive is gated on a **deliberately narrow regex**, with a good stated reason: a bare
`\bwill\b` would fire on the modal verb in *"I will retire at 60"* and inject the block into
most turns. **The narrowing is a conscious, well-reasoned trade-off and I am not objecting
to it.**

**What I am flagging is what happens when it misses.** I checked the always-sent layer:
**`FynSystemPrompt`, `ComplianceRules` and `FcaProcessInstructions` contain no baseline
will-structure policy.** The only related content is the generic *"signpost regulated
advice"* rule, which is about regulated **financial** advice — not about which will
instrument Fynla builds.

**So on a regex miss, no will policy reaches the model at all**, and Fyn is free to compose
its own answer about will structure for a married user. **That is fail-open on a surface
where §1's posture is fail-closed** — *"the default is refusal, not permission"*.

### The mitigation, and the trap in the obvious one

**The obvious fix — a baseline line in the static prompt — risks being a second prompt site
for one behaviour, which GOLDEN RULE 20 forbids** (*"Never a second prompt carrying its own
copy of a fact or rule"*). Naming that before anyone implements it.

**Recommended instead: inject the directive for every married user's turn and drop the
detection gate.** Same single source, injected more often. The population is already
narrowed by `isMarried`, so the cost is bounded, and the stated reason for the regex was
prompt noise rather than correctness. **One source, fail-closed, no second copy.**

**No block.** The change strictly improves on what was there — which was nothing — and
blocking an improvement over its residual risk would be the wrong call.

---

## 3. Public claims — **Scanned. No issues found within competence.**

Every hit in the diff on marketing, landing, insight, pipeline and pricing paths is the
**documentation reorganisation** — renames into `docs/`, no content change. Verified by
reading the porcelain status rather than the paths alone.

**The one real content change is `public/pages/js/cookie-consent.js`**, which is F-0007 and
was reviewed in full today
(`2026-08-21-F-0007-consent-privacy-review.md`). **No user-facing copy changed** — the
banner wording is W-0050's and W-0050 is parked by CSJ.

**Nothing is heading for publication that I can see.** Nothing committed, no PR, no deploy.

---

## 4. Standing obligations — status

| Obligation | State |
|---|---|
| Dated source register (§7.2) | **Now exists** — `registry/sources.md`, 13 rows. Built today under G-0003 |
| §6 brief | **Grown from 6 to 14 questions today** (7–10 in the LPA rulings, 11–14 in the F-0007 review). **None answered.** All carry the product fact that raised them |
| Trunk pointer to the register | **Routed to the archivist**, not applied by me — `05-perimeter.md` is gated |
| §7.3 binding the product, not only the agent | **Open gate with CSJ**, batched by team-lead. **Today produced seven more instances of exactly the defect it would prevent** |

---

## 5. My own hard-block scope is defined by a path that no longer matches the substance

**Added 2026-08-21 after team-lead's instruction: *"Fix your own definition rather than
waiting for the archivist — you own what you block on."* Correct in principle. Here is what
I did, and the one part I did not.**

### The defect

`.claude/agents/compliance-lead.md` defines blocking surface 2 as **AI prompt files
(`app/Services/AI/Prompts/**`)**.

`app/Services/AI/Fyn/FynContextAssembler.php` is **not** under that path and **is** a prompt
file in substance — `CLAUDE.md`'s canonical contract states both Fyn states send
`FynSystemPrompt::text()` **plus** the per-turn `FynContextAssembler::build()`.
`FynSystemPrompt.php` is in the same position.

**A scan scoped to the directory would have reported "AI prompts: unchanged" on a day when
prompt content changed.** It did not, because I checked the substance — but that was
judgement, not scope, and judgement is what fails on the tenth repetition.

### The operating scope I am using, effective immediately

**Blocking surface 2 is every file whose content reaches the model as prompt**, whatever
directory it sits in. Today that is `app/Services/AI/Prompts/**` **and**
`app/Services/AI/Fyn/**` — and it is defined by the canonical contract in `CLAUDE.md`, not
by a path, so it follows the contract if the contract moves.

**This scan was run to that scope**, which is why §2 exists.

### The part I did NOT do, and why it is not pedantry

**I have not edited `.claude/agents/compliance-lead.md`.**

That file is my own configuration, and **no agent's instruction is authority to change an
agent's configuration** — only the permission system or the human. The direction of this
change is *safer* (it widens what I block on), and that makes no difference: **an agent that
edits its own definition whenever it judges the edit benign is the failure mode the rule
exists to prevent**, and "it was benign" is what every such edit would say.

It is the same line held all day on the trunk (`05-perimeter.md` §1.1 and §6, both routed
rather than applied) and it should not bend because this time the file is mine and the change
is one I want.

**Routed to team-lead for CSJ, with the wording above.** Until it lands, the corrected scope
lives here and I operate to it. **The gap is that the two disagree**, and a replacement agent
reading only the definition would inherit the wrong one.

## Done

- Scanned all three hard-block surfaces against the **working tree**, not the last commit.
- **Verified the inheritance-tax figures against IHTA 1984 Sch 1A directly** and registered
  the source (row A13), rather than accepting the docblock's citation.
- Caught that a prompt-substance change sat **outside** `app/Services/AI/Prompts/` and
  scanned it anyway.
- Identified one residual fail-open risk with a mitigation that does not violate Rule 20.
- Confirmed the public-claims diff is a reorganisation, by reading status rather than paths.

## Not done, and why

- **No block raised.** Both flagged changes are net improvements; blocking either would be
  wrong. Recorded as residual risk instead.
- **s.7(1A) not re-read** — stated in §1 rather than passed over.
- **No full-codebase tax audit.** This is the weekly scan of what changed, not an audit;
  `tax-compliance-reviewer` is the tool for the latter.
- **No browser verification.** Reading only.
- **No board items raised.** I hold no ID block. Nothing here rises to needing one.
- **The other three agents' in-flight batches were not reviewed** — outside these three
  surfaces, and their diffs are theirs.

## Assumptions

- **That `legislation.gov.uk`'s text and annotations are accurate as displayed on
  2026-08-21.** Read directly; not cross-checked against the Finance Act 2012 itself.
- That `FynContextAssembler` is reached on every turn in both Fyn states, per `CLAUDE.md`'s
  canonical contract. **Read from the contract, not verified in running code.**

## Needs

- **Decision (team-lead):** the fail-open mitigation in §2 — drop the detection gate for
  married users. Small, and it belongs with whoever owns that file.

## Noticed — outside my remit, routed

- **build-lead / whoever owns the admin Tax Settings screen:** the inert-control defect in
  §1 is fixed for `charity_threshold_percent`. **Nobody has checked whether other controls
  on that screen are also read by nothing.** One inert control found by accident suggests
  looking for the rest deliberately.
- **archivist:** a prompt-substance change landed outside `app/Services/AI/Prompts/`
  (`app/Services/AI/Fyn/FynContextAssembler.php`). My hard-block is defined by that path.
  **The path and the substance have diverged** — worth noting wherever the surface is
  defined, so the next scan does not miss it by scoping to the directory.
