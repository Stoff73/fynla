---
id: W-0497
title: The estate strategy and onboarding text meets the user cold with six acronyms — RNRB, NRB, IHT, PET, CLT and GROB
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [design-lead, quality-lead]
status: done
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

## 2026-09-01 — CLOSED

**The unit is the method, because the method is the screen.**
`TrustPlanningStrategy.vue` renders `strategy_name` (`:115`), `description` (`:116`),
`implementation_steps` (`:209`), `key_benefits` (`:264`) and `key_risks` (`:279`) in one
card, and each strategy method returns exactly one card. That is what makes CSJ's
2026-08-24 amendment operable here: expand at the top of the card, abbreviate below it.

**Acceptance 1 — 20 strings changed across the three files.** The expansions were placed
in `strategy_name` and `description`, which the reader meets first, so the steps,
benefits and risks below may keep the short form.

**Acceptance 2 — the judgement, recorded so nobody "fixes" a compliant string:**

| Left abbreviated | What introduces it, on the same screen |
|---|---|
| `'✗ Immediate IHT charge if over NRB (20%)'`, `'✓ Assets immediately outside your estate for IHT'`, `'✗ 10-year anniversary charges (6% on value above NRB)'` | the description above them: *"…using your available Nil Rate Band (NRB) — the amount that passes free of Inheritance Tax (IHT)"* |
| `'✓ Maximize NRB usage over multiple cycles'`, `'✓ No immediate tax if each transfer within NRB'`, `' (NRB resets after 7 years)'` | *"Multi-Cycle Chargeable Lifetime Transfer (CLT) Strategy"* + the description expanding NRB and IHT |
| `'3. **Loan remains in your estate** for IHT purposes'`, `'4. **Investment growth is outside your estate** (IHT-free)'` | the Loan Trust description, now *"…free of Inheritance Tax (IHT)"* |
| `'✓ Reduced CLT value due to discount'`, `'✗ Still subject to 7-year rule on CLT value'`, `'✓ Lower IHT charge than full gift'` | the Discounted Gift Trust description, now expanding both CLT and IHT |
| `'✓ RNRB available on main residence'`, `'4. Avoids double IHT charge on same property'`, `'4. Complex and may not save significant IHT'` | the Property Trust description (IHT) and step 4, now *"Claim the Residence Nil Rate Band (RNRB)…"* |
| `'Gift liquid assets now (becomes PET…)'` | `:204` in the same method: *"…using Potentially Exempt Transfers (PETs)"* |
| the second and third `IHT` inside `:104`, `:134` | the first use in the **same string**, now expanded |

**`getSteps()` was treated more strictly than the rule requires, deliberately.** It
returns many onboarding steps and **each step is its own screen**, so an expansion in the
income step does not introduce the term for the beneficiary step. Every step that names
an acronym now expands it in its own text. The item says over-expanding is acceptable and
under-expanding reintroduces the defect; this is the safe direction.

**Acceptance 3 — `/m` and native.** Neither holds a copy of any of these strings, and
neither renders `strategy_name`, `implementation_steps`, `key_benefits` or `key_risks` at
all — greps return nothing. There is one home, so both inherit by construction; the
strategy cards are a web surface today.

**Acceptance 4 — no glyph changes.** `git diff` shows **zero** lines containing ✓, ✗ or
⚠️ added or removed. Rule 15's grandfather clause held.

**Guard:** `tests/Feature/Estate/EstateAcronymsAreExpandedOnTheirOwnScreenTest.php` —
per method, an acronym in any string literal must have its expansion in a literal of the
same method. A second case fails if the glyphs are tidied away.

**Two instrument errors, recorded because the first offender list was wrong twice:**
1. A regex for quoted literals is wrong on PHP source — an apostrophe inside a docblock
   (`User's IHT profile`) opens a bogus string and the scan then swallows comments and
   code. It reported offences in `__construct()` and `calculateNRBAvoidanceProjection()`
   that do not exist. Replaced with `token_get_all`.
2. Tracking the method name as "the `T_STRING` after `T_FUNCTION`" misfiles literals
   under `toArray`, because an anonymous `function ($x)` has no name token. The flag now
   clears on the first non-whitespace token.

Tests: **47 passed** across TrustStrategy / GiftingStrategy / EstateOnboarding / Acronym.
Three assertions in `PersonalizedTrustStrategyServiceTest` pinned the old wording as a
contract — corrected with the reasoning at each line, not loosened to substring matches.

**Not done:** the `design-lead` and `quality-lead` reviewers on this item's front matter
were not run — no agent was dispatched, per the session instruction. No browser drive.
