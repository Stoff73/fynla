---
id: W-0048
title: The Tailwind safelist explicitly guarantees non-palette colours compile forever — this is why Rule 11 breaches survive
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
reviewers: [build-lead]
status: deferred
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

## Measurement 2026-08-26 — no colour changed, park respected

Re-measured because the ledger dates from 2026-08-21. **Nothing in this section
edits a colour; it establishes what is true today so the parked decision can be
taken on current figures.**

### The count has not run away

Non-palette colour utilities across `resources/js`, `resources/views`,
`resources/css`:

| Family | Occurrences |
|---|---|
| blue | 222 |
| green | 148 |
| teal | 122 |
| red | 98 |
| pink | 81 |
| gray | 59 |
| slate | 55 |
| emerald | 29 |
| yellow | 20 |
| fuchsia | 17 |
| rose | 15 |
| cyan | 11 |
| **total** | **877** |

Consistent with the 916-occurrence ledger. **The park has held** — this is not
growing while it waits.

### The safelist is unchanged and still guarantees four non-palette families

`tailwind.config.js:9-13` still safelists `green`, `teal`, `blue` and `red` at
seven utilities each. The item's diagnosis stands: those compile forever, nothing
warns, and Rule 11 has no mechanism behind it.

### The unbuildable clause is worse than recorded — it is live, and it is used

The item flags `bg-light-blue-100 text-light-blue-700` in the guide as a build
error. Two corrections, both verified:

**It is not a build error.** Tailwind does not fail on an undefined utility; it
simply emits nothing. `.text-light-blue-700` appears in **no** compiled stylesheet
under `public/build/assets/`, while `.text-raspberry-700`, `.text-violet-700` and
`.text-spring-700` all do.

**And it is not confined to the guide.** `resources/js/views/Public/insights/
InsightsHubPage.vue:324` uses it for the 'Platform updates' insight tag:

    'Platform updates': 'bg-light-blue-100 text-light-blue-700',

Proven in a browser rather than argued: a span carrying those two classes was
rendered inside a host with `color: magenta`. It came back **`#FF00FF`** — the
inherited colour — with the correct `#DDE2EF` background. Its siblings resolved
`#6D28D9` and `#047857` correctly.

**So on a public page, that badge has a coloured background and text in whatever
colour it inherits.** It is a class that does nothing, not a near-miss on contrast.

`light-blue` defines **100 and 500 only** (`tailwind.config.js:106-109`). The guide
specifies the broken pair twice, at lines 797 and 822.

### Contrast, if it is ever fixed

| Pair | Ratio | AA |
|---|---|---|
| `light-blue-500` on `light-blue-100` | **2.89:1** | fail |
| `neutral-500` on `light-blue-100` | 3.76:1 | fail |
| **`horizon-500` on `light-blue-100`** | **11.00:1** | **pass** |

The item's 2.9:1 figure is correct. `horizon-500` is the palette-correct pair that
also passes — worth recording so the decision is not re-derived later.

### What the existing mechanism already covers, and what it misses

`.claude/hooks/design-lint.sh` (repaired under W-0491) greps changed files for
banned colour tokens and blocks on a hit — forward-only, changed files only, so it
cannot fire on the 877. But its pattern is:

    (amber|orange)-[0-9]{2,3}|gray-[0-9]{2,3}|(primary|secondary)-[0-9]{2,3}

**It does not match `blue`, `green`, `teal`, `red` or `pink`** — the four families
the safelist guarantees, plus the one in `app.css:323`. So the mechanism acceptance
1 asks for is **half-built already**, and widening the pattern is a lint change that
edits no colour.

`resources/mobile/` remains outside it entirely, per acceptance 3 — the hook only
inspects `*.vue|*.js|*.css`, which does cover `resources/mobile/style.css`, but the
pattern is Tailwind-class-shaped and `/m` uses hex and custom properties. W-0081
cleared `/m`'s hex; nothing stops it returning.

### Still parked, and correctly so

Acceptance 2 (trim the safelist), the 877-occurrence migration, `app.css:323`, and
the Risk module's five-step ramp are all untouched and stay that way under CSJ's
decision. Nothing above changes a rendered colour.

### Decisions taken 2026-08-26 — Azlan

Both halves of the above were put to Azlan rather than acted on, because CSJ's park
governs and the mechanism call is design-lead's.

**1. The mechanism — NOT built. "Leave it and raise in future if still required."**

`design-lint.sh`'s pattern is deliberately left as it is. It does not match `blue`,
`green`, `teal`, `red` or `pink`, so new occurrences in those families are not
caught. **That is a known, accepted gap while the park holds**, not an oversight:
widening the pattern is a lint change that edits no colour, it was offered on that
basis, and the answer was to leave it.

To be re-raised when this item unparks — at which point it is the cheapest half of
acceptance 1, since the hook already exists, already runs, and is already
forward-only.

**2. The broken tag — RAISED, NOT FIXED.** `text-light-blue-700` at
`InsightsHubPage.vue:324` emits nothing and the tag inherits its colour. Written up
with the browser evidence as **W-0503**. `fynlaDesignGuide.md:797` and `:822`
specify the same unbuildable pair and are likewise left alone.

**Nothing in this item has edited a colour, a class, the safelist or the guide.**
The park is intact.

- 2026-08-30 build-lead: **stamp corrected, `blocked` → `deferred`.** It carried no
  `blocked_by` and no `gate`, so nothing recorded what it was waiting for — while its own
  closing note says the park is intact and the item is to be re-raised when it unparks.
  A `blocked` item with no blocker is indistinguishable from a forgotten one, which is
  how W-0350 sat six days after its blocker had been lifted. Nothing else touched.
