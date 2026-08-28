---
id: W-0503
title: The 'Platform updates' insight tag uses text-light-blue-700, a class Tailwind never emits, so the text takes whatever colour it inherits
mission: M-0002-persona-fidelity
owner: design-lead
reviewers: [build-lead]
status: queued
severity: low
surfaces: [web]
source: found while measuring W-0048, 2026-08-26
prior_art_checked: 2026-08-26
prior_art_found: [W-0048]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

`resources/js/views/Public/insights/InsightsHubPage.vue:324` colours the insight
category tags:

    'Tax changes':      'bg-raspberry-50 text-raspberry-700',
    'Pensions':         'bg-violet-50 text-violet-700',
    'Savings & ISA':    'bg-spring-50 text-spring-700',
    'Estate planning':  'bg-violet-50 text-violet-700',
    'Platform updates': 'bg-light-blue-100 text-light-blue-700',

`tailwind.config.js:106-109` defines `light-blue` at **100 and 500 only**. There is
no 700, so `text-light-blue-700` is not a real utility.

**Tailwind does not fail on an undefined utility — it emits nothing.** So the
background applies and the text colour does not. The tag renders in whatever colour
it inherits from its container.

## Proven, not argued

`.text-light-blue-700` appears in **no** compiled stylesheet under
`public/build/assets/`, while `.text-raspberry-700`, `.text-violet-700` and
`.text-spring-700` all do.

Confirmed in a browser on `/insights`. A span carrying the two classes was rendered
inside a host set to `color: magenta`, so "no colour applied" would be visible:

| Tag | Classes | Computed colour | Computed background |
|---|---|---|---|
| **Platform updates** | `bg-light-blue-100 text-light-blue-700` | **`#FF00FF`** — the inherited magenta | `#DDE2EF` |
| Pensions | `bg-violet-50 text-violet-700` | `#6D28D9` | `#F5F3FF` |
| Savings | `bg-spring-50 text-spring-700` | `#047857` | `#F0FDF9` |

The background resolves and the colour does not. That is the defect.

**No seeded article currently carries the 'Platform updates' category**, which is
why it has not been noticed — the map entry is live but unexercised against seeded
data. It will render the moment an article uses that category.

## The fix, when it is taken

`text-horizon-500` on `bg-light-blue-100` measures **11.00:1**, comfortably above
the WCAG AA floor the design guide mandates, and is palette-correct.

The two alternatives do not pass: `light-blue-500` on `light-blue-100` is
**2.89:1** and `neutral-500` is **3.76:1**.

## Deliberately not fixed

**Raised rather than fixed on Azlan's instruction, 2026-08-26.** W-0048 carries
CSJ's park — *"leave the colours exactly as they are… no colour edits of any
kind"* — and although this is repairing a class that does nothing rather than
migrating one that works, it is still a colour edit and the call was Azlan's, not
an agent's.

`fynlaDesignGuide.md` lines **797 and 822** specify the same broken pair, so the
next reader who implements from the guide reproduces it. Left as-is for the same
reason; the guide is CSJ's document.

## Acceptance

1. `InsightsHubPage.vue:324` uses a text colour that Tailwind actually emits.
2. `fynlaDesignGuide.md:797` and `:822` corrected, or the missing `light-blue`
   shades defined in `tailwind.config.js` — whichever CSJ prefers. Both places
   currently describe a pair that cannot work.
3. Verified in a browser against an article that carries the 'Platform updates'
   category, since none does today. Seeding one is part of the check.
