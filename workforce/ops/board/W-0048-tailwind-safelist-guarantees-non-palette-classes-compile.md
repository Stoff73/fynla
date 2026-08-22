---
id: W-0048
title: The Tailwind safelist explicitly guarantees non-palette colours compile forever — this is why Rule 11 breaches survive
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
reviewers: [build-lead]
status: blocked
severity: medium
surfaces: [web]
source: root-caused by design-palette-fix during the W-0045 sweep, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent — the mechanism, not another instance

`tailwind.config.js:8-15` safelists non-palette Tailwind colour utilities explicitly:

```js
safelist: [
  // Risk level colors - must always be included (used dynamically)
  'bg-green-50', ... 'ring-green-400',
  'bg-teal-50',  ... 'ring-teal-400',
  'bg-blue-50',  ... 'ring-blue-400',
  'bg-red-50',   ... 'ring-red-400',
```

**So `blue-*`, `green-*`, `teal-*` and `red-*` are guaranteed to compile, forever.**
Nothing fails, nothing warns, no linter fires. Rule 11 is a rule with no enforcement
behind it, and that is why 807 occurrences across 75 files survived in
`resources/js/` alone, plus 103 in `welcome.blade.php` and 6 in `resources/css/app.css`.

Compare W-0031 and the `ProfileEnums` chain: the same class of problem — a rule kept by
memory rather than by mechanism — solved by making the drift fail a test. **This item
is that fix for colour.**

## Why it cannot simply be deleted

The safelist comment is accurate: the risk module genuinely uses those classes
dynamically, and removing the safelist without remapping the risk module would break
those surfaces at build time rather than fix them. **The safelist is a symptom of the
risk-module decision (see below), not an independent mistake.**

## Acceptance

1. **A mechanism that fails** when a non-palette colour class is introduced — the
   colour equivalent of `ProfileEnumColumnsTest`. A lint rule, a test that greps the
   built CSS, or a Tailwind config that simply cannot emit non-palette utilities.
   Design-lead's call which, but memory is not a mechanism.
2. The safelist is reduced to palette tokens only, **after** the risk-module decision
   below is taken — not before, or the build breaks.
3. Whatever is chosen must cover `resources/mobile/` too, which does **not** use
   Tailwind. It hardcodes hex in `resources/mobile/style.css` and invents
   `--neutral-400` / `--neutral-600` where the palette defines `neutral-500` only. A
   Tailwind-only guard would leave `/m` unprotected and look complete.

## The blocking decision — CSJ / Azlan

The **risk module** (11 files, **276 occurrences** — the largest single cluster) uses a
deliberate five-step `green → teal → blue → red` risk spectrum. **The Fynla palette has
no five-step sequential scale.** So this is a design decision — invent a palette
sequential scale, or accept a documented exception for the risk spectrum — and it
belongs to Azlan, not to an agent. Nothing in this item can complete until it is taken.

## Related finding — the design guide contains an unbuildable clause

`fynlaDesignGuide.md`'s Info badge variant specifies `bg-light-blue-100
text-light-blue-700`. `tailwind.config.js:106-109` defines `light-blue` at **100 and
500 only**, so `@apply text-light-blue-700` is a **build error**, not a near-miss. The
nearest defined pair (`light-blue-500` on `light-blue-100`) measures **2.9:1**, below
the WCAG AA floor the guide itself mandates.

The guide needs correcting — either define the missing shades or specify a pair that
both exists and passes contrast. **CSJ owns the guide; flagged, not amended.**

- 2026-08-21 team-lead: **CSJ DECISION — park. Leave the colours exactly as they are.**
  CSJ: *"keep it as it currently is, and we will sort once all bugs are sorted, this is
  low priority; getting the system functional in the way it should be for real users is
  the priority."*
  So: **no palette migration, no safelist trimming, no colour edits of any kind** until
  the functional defect board is clear. That covers the Risk module's 276-occurrence
  five-step green→teal→blue→red ramp (which needed Azlan's call and now does not need it
  yet), the 916-occurrence ledger, and `app.css:323` (`.badge-vct` / `.badge-eis`
  `bg-pink-100 text-pink-800`) — **the earlier authorisation for `app.css:323` is
  WITHDRAWN under this decision; it never landed, so the file is untouched and stays
  that way.**
  When it is unparked, the three constraints from W-0082 still govern: `app.css:323` is
  the highest-leverage single line; the Risk ramp is a design decision, not a token swap;
  and safelist entries are removed **LAST per cluster, not first**.

- 2026-08-21 team-lead: **W-0082 FOLDED INTO THIS ITEM AND CLOSED.** The two were written
  in parallel by agents who could not see each other and cover the same root cause (the
  Tailwind safelist) and the same sweep ledger. This item wins: it carries the reviewer
  chain (`reviewers: [build-lead]`) and was raised first. W-0082's three surviving
  constraints are recorded in the note above; nothing from it is lost.
