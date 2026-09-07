---
id: W-0208
title: The letter/will consistency check flags a punctuation difference as an executor mismatch and tells the user to edit a legal document
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: done
severity: medium
surfaces: [web]
created: 2026-08-22T01:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0022]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 3 journey re-walk, local, `david.jones@example.com`, read-only.
**Surface:** `/valuable-info?section=letter` → Letter Consistency Checks.

### Expected

A consistency check that concerns a will should fire on a real difference — a different
executor, a missing one, an extra one. Two identical executors in the same order are
consistent however the list is punctuated.

### Actual

```
Letter Consistency Checks
  The executor named in your letter ("Sarah Jones & Barclays Wealth") does not match the
  executor in your will ("Sarah Jones, Barclays Wealth"). These should be consistent to
  avoid confusion.
  Update either your letter or your will so the executor details match.
```

**The two strings differ by one character: "&" against ",".** Same two executors, same
order, same spelling. The check is comparing raw strings and reporting a punctuation
difference as a substantive mismatch — then instructing the user to go and change one of
their legal documents to resolve it.

### Impact

This fires on a screen about a will, in the register of things a bereaved family will
rely on, and the remedy it proposes is editing a legal document to fix a problem that
does not exist. It will fire for essentially any user whose letter joins two names with
"&" — a very common way to write it.

Beyond the false alarm, it devalues the checks around it. Two of the other three warnings
on the same panel are genuinely useful and correct — "You have 2 vehicles recorded as
chattels, but your letter does not mention vehicle information" (David has exactly two)
and the equivalent for three valuables. A user who dismisses the first warning as noise
is likely to dismiss those too.

### Repro

1. `david.jones@example.com` → `/valuable-info?section=letter`, wait ~15s.
2. Read the first consistency check. Compare the two quoted strings character by
   character: they differ only in the separator.

### Acceptance

1. Executor comparison normalises before comparing — separators, case, ordering and
   surrounding whitespace — and compares the **set of names**, not the rendered string.
2. A genuine mismatch (a different or missing executor) still fires, proven by test.
3. Where a difference is only in formatting, either say nothing or say plainly that the
   wording differs but the people match — never instruct a legal-document edit for it.
4. Verified in a browser against a matching pair, a punctuation-only difference, and a
   genuinely different executor.

---

## Verified 2026-09-01 — already fixed, and the fix holds under mutation

**Checked in the code, not taken on trust.** The comparison is party-set based, not
string based:

- `LetterEstateValidationService::executorsMatch():268-272` compares
  `willExecutorParties()` against `partySet()` — sets, not rendered strings.
- `partySet():309-313` splits on `,`, `;`, `&` and the word `and`.
- `asPartySet():318-325` de-duplicates and sorts, so ordering cannot matter.
- `normaliseParty()` handles case and titles.
- `willExecutorParties():285-296` reads `will_documents.executors` — the structured
  source — where there is one, and falls to the string only for a will captured as free
  text through Fyn or onboarding. That is acceptance 1 done properly: it does not
  re-split a derived copy when the fact is available (Rule 20).

The reasoning for all of it is written at `:255-267`, naming this item.

**Acceptance 1 and 3 — satisfied.** Separators, case, ordering and whitespace are
normalised, and a formatting-only difference produces no warning at all, so no user is
told to amend a legal document over an ampersand.

**Acceptance 2 — proven by test.** `tests/Unit/Services/Estate/LetterEstateValidationServiceTest.php`
— 18 passing, including "it still warns when one of the two executors differs" and "it
still warns when the letter names only one of two". The comparison is not merely
loosened.

**Mutation-verified.** Narrowing `partySet()`'s separators back to `,` alone turns three
tests red — both punctuation-equivalence cases and the will-document case. The guard
bites.

### Acceptance 4 — verified against the repro's own data, not in a browser

Run directly against `david.jones@example.com`, the account in the repro:

```
will executor:   Sarah Jones & Barclays Wealth
letter executor: Sarah Jones & Barclays Wealth
EXECUTOR WARNINGS: 0
```

**The punctuation-only and genuinely-different cases were NOT re-driven through the
browser** — both strings on that account now read identically, so the live data no
longer reproduces the difference, and forcing one would mean editing a database row.
Those two cases are covered by the passing tests above and by the mutation run. Stated
plainly rather than described as browser-verified.
