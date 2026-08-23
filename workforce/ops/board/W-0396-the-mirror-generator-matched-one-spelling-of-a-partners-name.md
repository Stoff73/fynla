---
id: W-0396
title: The mirror generator matched one spelling of each partner's name, so a recorded middle name silently reopened W-0024
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: build-lead
reviewers: [quality-lead, compliance-lead]
status: handoff
severity: high
surfaces: [web, m]
created: 2026-08-23T01:25:00Z
claimed: 2026-08-23T01:25:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0024, W-0395]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

**Found by writing W-0395's tests, not by the persona and not by any tester.**

`generateMirrorWill()` matched each partner on ONE spelling, and built the two
sides from different sources:

- the primary's from the will's `testator_full_name`;
- the partner's from `first_name + middle_name + surname` off the profile.

`isSameParty()` compares case and whitespace only, deliberately. So a partner
with a middle name **recorded on the profile** but named in the will **without
it** matched neither candidate, the swap found nothing to do, and the generated
mirror kept the primary's executor list verbatim.

**That is W-0024's exact symptom, still reachable after W-0024 was fixed:** a
will appointing its own testator as executor, describing her as her own spouse,
and persisting both into `wills.executor_name`.

### Why nothing caught it

W-0024's own tests give the partner no middle name, so the two spellings
coincide: the right answer and the wrong answer are the same string. The
`peak_earners` household has no middle names either. **No amount of testing on
this persona or that suite could have found it** — this is the Collision variant
of `tests/CLAUDE.md` §4, structural rather than incidental.

Demonstrated: reducing the name candidates back to one spelling turns 3 cases red
and leaves **the entire 38-case `WillDocumentServiceTest` green** against a
generator that produces a legally incoherent will.

## Fix

`WillDocumentService::nameVariants(?User, ?string $asWritten)` — every spelling
the person's OWN records give them, most authoritative first: the will's own
wording, the full profile name, and the same name without the middle. The first
entry is what gets written; the rest exist only to recognise the same person.
`swapPartiesForMirror()` now takes candidate lists on both sides.

**This is NOT a loosening of `isSameParty()`**, which stays conservative because
two people can share a name and guessing at nicknames in a legal document would
be worse than the bug. Nothing here is inferred, abbreviated or invented — no
initials, no nicknames, no surname-only. Asserted explicitly in the tests.

### Deliberately not applied to `validateDocument()`

The executor-is-testator check still compares one spelling, and that is a
decision, not an oversight. **That check BLOCKS completion**: a false positive
stops someone finishing their will, and a father and son can share a name where
only one has a middle name recorded. The generator and the repair CHOOSE a
replacement, and their alternative is a document that appoints its author as
their own executor. Different cost of error, different test. Recorded in the
code so it does not read as a gap.

## Acceptance

- [x] A mirror generated for a partner whose will omits their recorded middle
      name appoints the primary, not the partner.
- [x] Guardians and residuary swap on the same basis.
- [x] Third parties keep their name and recorded relationship.
- [x] No invented variant: initials, nicknames and surname-only are asserted
      absent.
- [x] Mutation-tested — reducing to one spelling turns exactly the W-0396 cases
      and the affected repair case red.
- [ ] `compliance-lead`: this widens the class of households affected by W-0024's
      original defect. **The production question W-0024 raised is now larger than
      it was** — any pre-fix mirror is broken, and so is any post-fix mirror where
      the partner has a middle name recorded. Both are repaired by
      `estate:backfill-mirror-parties`.


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

- 2026-08-23 build-lead: found, fixed and covered in the same sitting.
  `tests/Unit/Services/Estate/MirrorWillPartyRepairTest.php`, the
  "recognised under every spelling" block. Not self-certified.
