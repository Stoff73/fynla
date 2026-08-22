---
id: W-0080
title: Education level labels carry acronyms (GCSE, O-Levels, A-Levels) and had four unbound renderers
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
status: handoff
severity: low
surfaces: [web, m]
source: raised by fix-batch-C during the W-0031 option-list work, 2026-08-21; routed to design-lead by team-lead as a copy decision
claimed: 2026-08-21
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_outcome: extend
constitution_refs: [04-voice, 07-quality-bar]
---

## Intent

`CLAUDE.md` Rule 9 — *No Acronyms in User-Facing Text*: "All acronyms must be
spelled out in user-facing text... The only exception is **ISA**."

The education level select offered **"Secondary (GCSE/O-Levels)"**. `fix-batch-C`
carried it verbatim to `/m` rather than diverge the wording, which was the right
call in the moment — one field reading differently on three surfaces is a worse
failure than an acronym — and flagged it for proper resolution.

**This is a copy decision, not a CSJ decision.** It was routed to CSJ; team-lead
corrected that. Rule 9 is unambiguous and has no length exemption — W-0021 settled
that precedent on the trust badge ("if the full phrase does not fit, the design
change is agreed rather than the acronym retained").

## Two labels, not one

The item named `secondary`. The sweep the dispatch asked for found a second:

| value | was | now |
|---|---|---|
| `secondary` | Secondary (GCSE/O-Levels) | **Secondary School** |
| `a_level` | A-Levels/Vocational | **Advanced Level or Vocational** |
| `undergraduate` | Undergraduate Degree | unchanged |
| `postgraduate` | Postgraduate Degree | unchanged |
| `professional` | Professional Qualification | unchanged |
| `other` | Other | unchanged |

Reasoning:

- **`secondary`** — the parenthetical existed only to name two acronym
  qualifications. Once neither may be written as an acronym it earns nothing:
  the full forms are "General Certificate of Secondary Education" and "Ordinary
  Level", the second abolished in 1988 and unrecognisable to most users spelled
  out. Dropping the parenthetical is the fix. The ladder position (directly above
  "Advanced Level") is what disambiguates it, which is how such a select reads
  anyway.
- **`a_level`** — "A" is short for "Advanced", so it falls under Rule 9 on the
  same reading that made "RPT" fall under it. **This is the borderline judgement
  in the change** — "A-Level" is arguably a proper name rather than an acronym.
  Flagged rather than buried; happy to revert this one alone if design disagrees.
- Case is deliberately left as Title Case to match the four labels that did not
  change. See the finding below — the file's house style is actually sentence
  case, and education is the outlier, but normalising it is a separate copy pass
  and would have churned the tester's playbook mid-run for no Rule 9 gain.

Health and smoking labels were checked in the same pass: **all 15 labels in the
file are acronym-free.** The surrounding field labels ("Education Level", "Health
Status", "Smoking Status") are clean too.

## The real defect: four renderers, two of them unbound

The dispatch said to change the label "in the one place that feeds all three".
There was no such place. There were **four** renderers of this copy:

1. `resources/js/constants/profileOptions.js` — desktop
2. `resources/mobile/constants/profileOptions.js` — `/m`
3. `app/Services/Protection/ComprehensiveProtectionPlanService.php:206` — a private
   `match` expression **nothing compared against**
4. — no backend home for the copy at all; `ProfileEnums` held values only

The parity spec bound 1 and 2. **Nothing bound 3.** It would have gone on
rendering "Secondary (GCSE/O-Levels)" into protection plan output after both
selects were corrected, and no test would have gone red. That is the same disease
Rule 20 exists for, one layer down from Fyn.

So the single home was created rather than assumed:

`App\Constants\ProfileEnums::EDUCATION_LEVEL_LABELS` — a value => label map. The
protection service reads it. Both frontend copies mirror it and are now pinned to
it **label by label**, not just value by value, by the two parity specs.

The chain end to end:

```
users columns ─▶ ProfileEnums (values + labels) ─▶ resources/js ─▶ resources/mobile
                        │
                        └─▶ ComprehensiveProtectionPlanService
```

## What changed

- `app/Constants/ProfileEnums.php` — new `EDUCATION_LEVEL_LABELS`; class docblock
  updated to say it now holds copy as well as values.
- `app/Services/Protection/ComprehensiveProtectionPlanService.php` — the private
  `match` deleted, reads the constant. Behaviour verified identical for every
  value including `null`, `''` and an unknown value (all still "Not specified").
- `resources/js/constants/profileOptions.js` — two labels; docblock now says
  labels are mirrored, not authored, here.
- `resources/mobile/constants/profileOptions.js` — same.
- `resources/js/components/__tests__/UserProfile/ProfileOptionsParity.spec.js` —
  `phpLabelMap()` helper, plus two new tests.
- `resources/mobile/__tests__/profileOptionsParity.spec.js` — same.

The new tests are:

- **"renders the education labels the backend authored, in order and word for
  word"** — pins JS copy to `EDUCATION_LEVEL_LABELS`.
- **"spells out every education acronym — Rule 9 allows only ISA"** — shape-based
  (`/\b[A-Z]{2,}\b/` and `/\b[A-Z]-Level/`) rather than a denylist of the three
  known offenders, so a *newly added* acronym is caught too. This is the test that
  would have stopped the original defect.

## Acceptance

- [x] Every acronym in the education list spelled out or removed — both, not just
      the reported one.
- [x] Full label set checked, not only education. All 15 clean.
- [x] Changed in one place that feeds all surfaces; the single home was created
      because it did not exist.
- [x] Parity specs green — 25 tests across three files.
- [x] **Specs proven to bite**: a divergent `/m` label was injected deliberately
      and three tests went red, including the acronym test naming the offender
      (`"Secondary (GCSE)" contains an acronym`). File restored, re-run green.
      A green run on a test that cannot fail is not evidence.
- [x] Pint and ESLint clean on the changed paths.
- [ ] **`tests/Unit/Database/ProfileEnumColumnsTest.php` NOT RUN** — needs
      `laravel_testing_e`, which does not exist. Requested from team-lead. The
      change adds a constant and touches no value list, so it should be
      unaffected — but that is reasoning, not a green run. **I COULD NOT TEST THIS.**
- [ ] Visual confirmation outstanding — tester owns the browser.

## iOS

Not a surface for this field. `ios-native/` has no `education_level` anywhere; the
"education" hits there are `education_funding` in Protection, an unrelated concept.

## Needs visual confirmation

Three selects render this list. All three read from the constants, so this is
confirmation rather than suspicion:

1. `/settings/health` — Health & Lifestyle, edit mode: the select, and the
   read-back value beneath it.
2. `/settings/personal` — Personal Information: the same select.
3. Onboarding → Personal Info step: "Highest Education Level".
4. `/m/app/personal-information` — the `/m` select and its read-back.

**Tester coordination — `tests/Persona/20-08-2026_run/PASS-PLAYBOOK.md` is
unaffected.** It asserts "Postgraduate Degree" at lines 92, 97 and 990, and that
label is deliberately unchanged. Only `secondary` and `a_level` moved, and neither
appears in the playbook. Not edited by me — it is persona-tester's artefact.

## Working notes

(append-only)

- 2026-08-21 design-lead: fixed. The borderline call is `a_level`; see above. The
  finding worth carrying forward is not the copy, it is that a fourth renderer sat
  outside the parity net — the same shape as W-0031, one layer up: values were
  pinned, copy was not.
- 2026-08-21 design-lead: **house-style finding, deliberately not acted on.** The
  health and smoking lists are sentence case ("Never smoked", "Yes, previous health
  conditions"); education is Title Case ("Undergraduate Degree"). Education is the
  outlier in its own file. Normalising it is a legitimate copy pass but it is not a
  Rule 9 matter, it touches four labels that are not defective, and it would have
  invalidated the tester's playbook strings mid-run. Raising it rather than doing
  it. Needs design-lead + product agreement, one pass, all four surfaces.
