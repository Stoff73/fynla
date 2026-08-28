# 00 — Precedence and Upkeep

**Status:** Ratified by CSJ, 2026-08-13, session 1 Q1.
**Owner:** CSJ. Amendments are gated — agents propose, CSJ ratifies.

This file governs the other documents: which one wins when two disagree, and how
all of them are kept honest over time.

---

## 1. Precedence

Ranked by **domain**, not by seniority or recency. A new document cannot outrank
an old one merely by being newer.

| # | Document | Supreme for |
|---|---|---|
| 1 | `CLAUDE.md` | Anything touching the codebase. Its ownership clauses (Rules 14, 15, 19, 20) override everything, including the Chief of Staff. |
| 2 | `core/constitution/05-perimeter.md` | Anything customer-facing or regulatory. **Until written, `app/Services/AI/Prompts/ComplianceRules.php` holds this role.** |
| 3 | `fynlaDesignGuide.md` | Visual decisions — except where `CLAUDE.md` Rules 8, 11, 12 and 15 override it, as Rule 10 already states. |
| 4 | `April/April19Updates/marketing/04-product-strategy.md` | **Nothing. Advisory only** until CSJ signs it. May be cited as evidence, never as authority. |

**Where two still conflict, the Chief of Staff stops and asks. It never picks.**

**Why ranked by domain.** Seniority ranking rots — every new document argues for
its own primacy, and the newest wins by default. Domain ranking means a document
only governs what it is actually competent to govern, and a conflict outside any
document's domain is correctly identified as a gap rather than silently resolved.

---

## 2. Upkeep

*Added by CSJ in session 1: doctrine grows, becomes cumbersome, and goes out of
date. Precedence is worthless if the winning document is stale.*

Doctrine is maintained, not merely accumulated. **Every document named in §1, plus
every file in `core/`, is subject to review.**

### 2.1 Continuous — verifiable facts

Part of the Archivist's nightly consistency sweep. Any assertion that can be
mechanically checked, is: file paths, counts, version numbers, rule numbers,
command names, cross-references. A stale verifiable fact is a defect and is fixed
the same way a broken log event is (§8.1 of the workforce design) — the fix is
autonomous, because correcting a number to match reality is not a doctrinal change.

### 2.2 Quarterly — full doctrine review

Run by the Archivist, checked by the Quartermaster, ratified by CSJ. Six checks:

| Check | Question |
|---|---|
| **Staleness** | Does this still describe reality? |
| **Dead doctrine** | Does the situation this rule governs still exist? |
| **Bloat** | Has the document grown past being read? |
| **Model calibration** | Was this rule written to compensate for a weaker model? |
| **Duplication** | Is this rule stated anywhere else? |
| **Unused** | Has any branch document or judgement cited this rule in six months? |
| **Practice drift** | Does the trunk say X while the branches consistently did Y? |

**Practice drift is the most valuable check** and only works because branch
documents declare which trunk clauses they apply. If twelve branches did Y while
the trunk says X, the likely fault is in the trunk. That is a question for CSJ,
not a correction to twelve branches.

**Unused rules are ambiguous, and that's the point.** A rule nobody cites is either
so internalised it can be retired, or being quietly ignored. Those need opposite
responses, so the review surfaces it rather than deciding.

**Model calibration — CSJ, session 5: as lean as possible for the latest models.**
Some rules exist because an earlier model needed the scaffolding, not because the
rule is load-bearing. Those are now cost: every unnecessary instruction consumes
context and dilutes the ones that matter. The review asks, of each rule, *would a
current model get this wrong without being told?* If not, it is a candidate for
removal — subject to §2.5's removal test, which still applies. **CSJ-owned rules
are exempt from this check** unless CSJ raises them; leanness never trims a
deliberate constraint.

### 2.3 Triggered

A review is also run when: a document crosses its size budget; a major
architectural change lands; or the same doctrine question is asked twice.

### 2.4 Size budgets

Bloat is invisible without a number. Budgets are advisory — crossing one triggers
a review, not an automatic cut.

| Document | Budget |
|---|---|
| `CLAUDE.md` | 40k characters. **Currently ~40.6k — over budget, review due.** |
| Any single `core/` file | 8k characters |
| `core/index.md` | 3k characters — it is a map, not a document |

### 2.5 Rules for the review itself

1. **Propose, never edit.** The review output is a diff with a rationale per
   change. CSJ ratifies. Trunk amendment is gated (session 1 Q3).
2. **Removal is riskier than addition.** A proposal to delete a rule must state
   what would happen if it were violated after removal, and confirm no branch
   document depends on it.
3. **Prune before adding.** A review that only adds has not been done.
4. **Never rewrite a rule's meaning while tidying its wording.** Editorial and
   substantive changes are proposed separately, so CSJ can approve one and not the
   other.

---

## 2.6 Queue — amendments landing in `CLAUDE.md`

Ratified decisions whose one home is `CLAUDE.md` rather than the trunk. **These are
ordinary items in the §2 upkeep regime, not blockers** — they are carried on the
review queue and applied like any other correction. Recorded here so the queue is
visible, not because anything is stuck.

Precedence note, for completeness: until an amendment is applied, `CLAUDE.md` as
written governs under §1. In practice nothing here is urgent — the acronym
exception affects only published marketing, which is gated regardless.

| Amendment | From | Status |
|---|---|---|
| **Rule 9** — permit acronyms on discovery surfaces (search keywords, meta, headlines answering an acronym query); expand on first use in body copy; unchanged everywhere else | Session 5, `04-voice.md` §4 | Queued |
| **`release` skill** — remove the "wait for CSJ's explicit go-ahead" / "never self-trigger" requirement, superseded by the evidence gate. **All verification steps and prohibitions unchanged.** | Session 9, `08-process.md` §6.1 | Queued |
| **`.github/CODEOWNERS` removal + `CLAUDE.md` branch-protection text.** Ratified session 1 Q2; **CODEOWNERS still exists and CLAUDE.md still says `dev`/`main` are protected.** Until applied, CLAUDE.md governs under §1 and merging remains CSJ's. | Session 1 Q2, `08-process.md` §1 | **Queued — highest priority.** The trunk's core merge doctrine is inert without it. |
| **Service count** in `CLAUDE.md` — 446→462 (verified 2026-08-13). **Persona count needs no change — six is correct.** | Session 9 | Queued |

## 2.7 Size-budget breaches — first review queue

Measured by `workforce/ops/sweep.sh` §4, 2026-08-13. **Findings, not faults** —
they enter the quarterly review under §2.5's prune-before-adding rule. None is
urgent; none blocks anything.

| File | Size | Budget |
|---|---|---|
| `charter.md` | 15,021 | 8,000 — **worst; nearly double** |
| `registry/capabilities.md` | 12,372 | 8,000 |
| `constitution/08-process.md` | 10,903 | 8,000 |
| `constitution/05-perimeter.md` | 8,329 | 8,000 |
| `constitution/00-precedence.md` | 8,005 | 8,000 |
| `CLAUDE.md` | 40,419 | 40,000 |

`index.md` was 4,749 against a 3,000 budget and **was trimmed to fit** — it is the
entry point every agent reads, so bloat there is paid on every session. The rest
are read on demand and can wait for the review.

**Candidate restatement flagged by the sweep:** "never verifies their own work"
appears in three trunk files. Its home is `08-process.md` §2.4; the other two
should be references. To be confirmed at review — the check flags candidates, it
does not judge.

## 3. Known staleness — outstanding

Recorded at ratification, pending the first review:

- `CLAUDE.md` states the vault holds "693 Obsidian docs". Actual count is **1,514**.
- `.goal:35` cites "CLAUDE.md Rule #15" for Loop Until Correct. It is **Rule 14**.
- `CLAUDE.md` is over its size budget.
- The component counts in `CLAUDE.md`'s overview table are unverified against the
  current tree.
- ~~`CLAUDE.md` lists six personas; there are seven.~~ **WITHDRAWN 2026-08-13 —
  this finding was wrong.** There are **six**; `widow` was removed 17 March 2026
  (commit `54b396a89`). `CLAUDE.md` was correct. The error came from matching dead
  code in `PreviewUserSeeder.php` rather than reading `private const PERSONAS`.
  Method recorded at `registry/capabilities.md` §2.

- **The vault's `Current State/` folder is 3–5 months behind the repo** (mtimes
  2026-03-02 to 2026-05-18; `Auth.md` self-labels "v0.7.0, 18 February 2026").
  Specifically wrong today: `PaymentSubscription.md` still describes three tiers
  and a seven-day trial — the tier collapse to free/premium landed **15 July 2026**
  (`2026_07_15_000000_collapse_tier_identity_to_free_premium.php`).
  `ConsoleCommands.md` claims `preview:reset` covers four personas including widow;
  it covers all six real ones. `Personas Index.md` names `retired_couple` as
  "Robert & Patricia Williams"; the code says Patricia & Harold Bennett.

- **`fynlaBrain/Home.md` presents the multi-country / South Africa pack programme
  as current status** (mtime 2026-07-24) — `packs/country-za/`, `core/app/Core/`,
  2,776 tests. **Neither `packs/` nor `core/` exists in the working tree.** The repo
  is on the campaign and iOS-native track. The vault's headline state contradicts
  the code, which matters because agents are told to read the vault before module
  work (`CLAUDE.md`). **Archivist priority.**

- `docs/archive/appMapping/v083/05-FRONTEND-ARCHITECTURE.md:242` claims "Seven personas"
  including widow — the document that caused the error above.
  `docs/archive/appMapping/v07/05-FRONTEND-ARCHITECTURE.md:204` says six but lists widow and
  omits student.
- `April/April19Updates/marketing/04-product-strategy.md` is **superseded in three
  places**, all found during sessions 3–4. It remains advisory elsewhere.
  - §2 segmentation and the "sub-£30k earners" exclusion → `01-mission.md` §2–3
  - §5 "subscription over freemium" → `03-hard-nos.md` §2. The app is now free
    tier forever plus **one** paid subscription upgrade.
  - Its four-tier £3.99–£29.99 pricing → stale, pending confirmation in session 7.
