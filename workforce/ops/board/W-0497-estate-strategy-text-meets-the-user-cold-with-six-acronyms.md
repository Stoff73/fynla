---
id: W-0497
title: The estate strategy and onboarding text meets the user cold with six acronyms — RNRB, NRB, IHT, PET, CLT and GROB
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [design-lead, quality-lead]
status: open
claimed_by: null
severity: low
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0454, W-0431, W-0432]
prior_art_outcome: extends
source: found while verifying W-0454, 2026-08-26
---

## Intent

W-0454 asked for the **allowance messages** to be swept for Rule 9. They were
already clean, and that item is closed on the evidence. The sweep turned up a
larger, separate body of user-facing text that is not.

**44 strings** carry an acronym the reader meets cold:

| File | Strings |
|---|---|
| `app/Services/Estate/PersonalizedTrustStrategyService.php` | 32 |
| `app/Services/Onboarding/EstateOnboardingFlow.php` | 8 |
| `app/Services/Estate/PersonalizedGiftingStrategyService.php` | 4 |

Six distinct acronyms: **RNRB, NRB, IHT, PET, CLT, GROB**. Read verbatim:

> `'4. Claim RNRB on remaining property value'`
> `'3. Gift released equity using PET/CLT strategies above'`
> `'2. They pay market rent for their share (avoid GROB)'`
> `'✗ Immediate IHT charge if over NRB (20%)'`
> `'Immediate Discretionary Trust (CLT)'`

These are `implementation_steps`, `key_benefits` and `key_risks` — the guidance a
user is expected to act on. **GROB** in particular ("gift with reservation of
benefit") is not a term a lay reader can look up from the initials.

## Why this is separate from W-0454, and must be done as one piece

W-0454's own reasoning: *"a Rule 9 sweep ... is its own small piece of work and
should be done as one, not one string at a time by whoever happens to be looking."*

That is exactly why the RNRB instances here were **not** picked off while verifying
that item. Fixing only those would have left the same block reading *"Immediate
Discretionary Trust (CLT)"* and *"No immediate Inheritance Tax charge (within NRB
of £325,000)"* — a half-converted sentence being worse than an honestly untouched
one, because it looks done.

## The Rule 9 amendment applies and narrows the work

**CSJ amendment 2026-08-24: spell it out once, then the acronym is fine** — on the
same string, the same screen, or an introduction screen preceding it. What is
banned is an acronym met **cold**.

So this is not a find-and-replace. Each string has to be judged against what the
user has already been shown *on that surface*:

- `'Immediate Discretionary Trust (CLT)'` introduces the term correctly; a later
  bare `CLT` **on the same screen** is then permitted.
- A bare `PET/CLT` with no expansion anywhere on that surface is not.

Getting this wrong in the safe direction — expanding everything — is acceptable but
clunky. Getting it wrong in the other direction reintroduces the defect.

## Do NOT tidy the icons while in here

These same blocks carry `⚠️`, `✓` and `✗`. Rule 15 is **forward-only** and these are
grandfathered: *"don't rip them out, flag them in audits, or tidy them up while
editing nearby."* Change the words, leave the glyphs. Removing them is a separate
decision and CSJ's alone.

## Acceptance

1. Every acronym in the three files above is either expanded, or demonstrably
   preceded by its expansion **on the surface that shows it**.
2. The judgement for each is recorded — which strings were left abbreviated and
   what introduces them — so the next reader does not "fix" a compliant one.
3. `/m` and native checked (Rule 19). These strings are API-sourced, so both
   surfaces inherit whatever is done here; confirm they render the same text.
4. No glyph changes.

## Related

- **W-0454** — the allowance-message sweep. Verified already satisfied 2026-08-26;
  this is what its sweep found.
- **W-0431, W-0432** — earlier Rule 9 work.
