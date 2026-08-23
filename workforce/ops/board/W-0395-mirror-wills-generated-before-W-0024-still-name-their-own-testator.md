---
id: W-0395
title: Mirror wills generated before W-0024 still appoint their own testator as executor — the fix could not reach documents already written
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: build-lead
reviewers: [quality-lead, compliance-lead]
status: gated
severity: high
surfaces: [web, m]
created: 2026-08-23T00:40:00Z
claimed: 2026-08-23T00:50:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0024, W-0046]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, cycle 4, D-13. Sarah Jones's executors read
`Sarah Jones · Barclays Wealth`. The persona says David Jones & Barclays Wealth.

**The dispatch asked which of two things is true — W-0024 unlanded, or pre-fix
residue. It is residue. W-0024 stays fixed.**

### The evidence, from a fresh generation rather than the existing row

`WillDocumentService::generateMirrorWill()` run on the live dev database against
a throwaway married pair, inside a transaction that was rolled back (0 rows kept,
verified):

```
FRESH MIRROR for spouse (Corin Probe)
  executors : ["Wren Probe" (Spouse), "Barclays Wealth" (Professional Executor)]
  guardians : ["Wren Probe" (Spouse)]
  gifts     : [{...,"beneficiary_name":"Cancer Research UK","copied_from_partner":true}]
  residuary : ["Wren Probe"]
```

Every party swapped. Nobody is their own executor.

**Three independent supports that `will_documents.id = 6` predates the fix:**

1. Generated **2026-08-21 08:59:21**; W-0024 was claimed at **09:40**.
2. Its `specific_gifts` carry **no `copied_from_partner` marker**, which the
   post-fix generator always writes (`markGiftsAsCopied()`) and the fresh
   generation above does.
3. Its residuary was already correct — the pre-fix behaviour, since
   `swapResiduaryForMirror` predates W-0024.

### Correcting the dispatch's supporting evidence

The dispatch reads Sarah's correct charity (British Heart Foundation rather than
David's Cancer Research UK) as proof that part of W-0024 landed. **It is not.**
The post-fix generator would have produced *Cancer Research UK* carrying
`copied_from_partner: true`. Document 6 holds British Heart Foundation and **no**
marker. That is the tester's own manual edit — recorded in W-0024's working notes
("I changed it to British Heart Foundation and the document regenerated
correctly") and corroborated by `updated_at` 09:03:33 against `created_at`
08:59:21.

### The damage was not confined to the document

`markComplete()` derives `wills.executor_name` from the document's executor list,
so the wrong names were persisted into Fynla's own record of the household's
intentions — `wills.id = 12` held `Sarah Jones, Barclays Wealth`. That is what
the Will Planning screen renders.

## Fix

`WillDocumentService::repairSelfNamedParties()` plus a new command
`estate:backfill-mirror-parties`, following the `estate:backfill-bequests`
pattern: dry-run by default, `--force` to write, `--user=` to scope, the whole
run in one transaction so a dry run reports the real outcome and rolls it back.

**Deliberately one-directional, and NOT the generator's swap.** Running
`swapPartiesForMirror()` over an already-correct document would exchange the
partners back and make the primary his own executor — a repair that manufactures
the defect it repairs. This replaces the testator's own name with their
partner's, wherever it appears as a party, and touches nothing else.

It writes the partner's name **the way this will already writes it** — the
document's own wording first, then the paired will's, then the profile — because
a repair reshapes a legal instrument and should reshape it as little as possible.

Selector and derivation both live on the service
(`namesItsOwnTestator()`, `executorNameFor()`), so the command, the repair and
`markComplete()` cannot drift apart (Rule 20).

### Applied

Dry run over **every** will document in the database: **one** match, document 6.
No false positives on the seven correct documents. Applied with `--force`.

```
wills.id 12 executor_name : Sarah Jones, Barclays Wealth -> David Jones, Barclays Wealth
doc 6 executors           : ["David Jones","Barclays Wealth"]
doc 6 residuary           : David Jones (unchanged, was already correct)
doc 5 / wills.id 11       : verified UNCHANGED
```

## Acceptance

- [x] Verdict stated with fresh-generation evidence: residue, W-0024 stays fixed.
- [x] Sarah's executors are David Jones and Barclays Wealth.
- [x] Nobody is their own executor — confirmed over live HTTP on both accounts.
- [x] `wills.executor_name` repaired, not just the document.
- [x] The repair cannot damage a correct will — asserted, and mutation-tested by
      reversing its direction (8 cases red, including the direction guard).
- [x] Idempotent; refuses an unmarried testator rather than guessing a partner.
- [x] **Rendered page read.** Sarah's Executors render `David Jones` and
      `Barclays Wealth`; David's are unchanged. Evidence below.
- [ ] `compliance-lead`: **W-0024's open GATE is LARGER than it was, not
      resolved.** A reader seeing "verdict: residue" will take it as good news;
      it is not. Two populations are affected on production, not one: **every
      mirror generated before W-0024** landed, **and every mirror generated
      after it where the partner has a middle name recorded** (W-0396). The
      question is still whether any real user has generated a mirror will on
      fynla.org. `generateMirrorWill()`
      landed in `9cfeadb46` (2026-03-16), which is on `origin/main`. **The command
      built here is the remedy if the answer is yes.** Not run against production;
      the `ssh-fynla` MCP is production and this batch is local-only.


### Browser verification — 2026-08-23, localhost:8000, Playwright

**Tab established as nobody** on arrival (both token stores empty) — checked
rather than assumed, and it was the state team-lead warned about. Logged in
through the real form on each account and confirmed identity with
`GET /api/auth/user` before reading anything: **id 16 David Jones**, then
**id 17 Sarah Jones**. `estate_analysis_16` / `_17` cleared by hand before each
read (W-0381).

Read verbatim off `/estate/will-builder`:

| | David (16) | Sarah (17) |
|---|---|---|
| Spouse line | `100% of your own estate to your spouse (£989,500)` | `100% of your own estate to your spouse (£739,280)` |
| Executors | Sarah Jones · Barclays Wealth | **David Jones** · Barclays Wealth |
| Specific Gifts | `£10,000 to Cancer Research UK` | `£10,000 to British Heart Foundation` |
| Residuary | Sarah Jones — 100% | David Jones — 100% |

The two estate figures **differ**, each is its owner's, and **neither £1,728,780
nor £1,716,780 appears anywhere on either page**. Nobody is their own executor.
Every gift names its recipient.

Screenshots:
`tests/Persona/20-08-2026_run/pass-a-web/150-web-david-will-own-estate-989500-executor-sarah-gift-named-W-0391.png`
`tests/Persona/20-08-2026_run/pass-a-web/151-web-sarah-will-own-estate-739280-executor-david-gift-named-W-0391-W-0393-W-0395.png`

## Working notes

- 2026-08-23 build-lead: fixed and applied locally.
  `tests/Unit/Services/Estate/MirrorWillPartyRepairTest.php` — 13 cases.
  Not self-certified — handed to quality-lead.
