---
id: W-0082
title: The Tailwind safelist licenses non-palette colours app-wide — 916 live Rule 11 breaches behind it
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
status: done
severity: medium
surfaces: [web]
source: found by design-lead during the W-0045 palette sweep, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

W-0045 fixed four trust components. The question it raised — *one instance
surviving means nothing prevented it* — has an answer, and the answer is a
mechanism, not an oversight.

**`tailwind.config.js:9-12`:**

```js
safelist: [
  // Risk level colors - must always be included (used dynamically)
  'bg-green-50', 'bg-green-100', ..., 'text-blue-700', ..., 'ring-red-400',
```

The safelist forces non-palette utilities to compile whether or not anything uses
them. Nothing fails, nothing warns, no linter fires, and the build is green. That
is why 916 occurrences accumulated across 80 files without a single signal.

## The ledger

Counted 2026-08-21, after W-0045 removed 39 from the Trusts module.

| Cluster | Occurrences | Files |
|---|---|---|
| **Risk module** | 276 | 11 — `views/Risk/*`, `components/Risk/*`, `Shared/RiskLevelSelector.vue`, `Shared/RiskBadge.vue` |
| **Public / marketing / Version** | 247 | 7 — `welcome.blade.php` 103, `Version.vue` 93, AboutPage 20, CalculatorsPage 19, Help 6, SitemapPage 4, ReviewCarousel 2 |
| **In-app module surfaces** | 315 | ~54 — heaviest: `LetterToSpouse.vue` 35, `ChattelFormModal.vue` 21, `BugReportModal.vue` 15, `StrategyDisclaimer.vue` 15, `RequiredCapitalDetail.vue` 14, `FutureValueTab.vue` 14, `ChattelDetailInline.vue` 14 |
| **Preview mode** | 64 | 3 — `PreviewBanner.vue` 36, `PersonaSelector.vue` 18, `PersonaIntroModal.vue` 10 |
| **Admin** | 8 | 4 — desktop-only by design, lowest priority |
| **`resources/css/app.css`** | 6 | see below |

By family: blue 245, green 152, teal 122, red 90, pink 79, slate 55, emerald 28,
yellow 20, fuchsia 17, rose 15, sky 12, cyan 11.

**Rule 8 is holding perfectly: zero `amber-*` and zero `orange-*` anywhere in
`resources/`.** Zero legacy `primary-*` / `secondary-*` / `gray-*` tokens too. The
hard bans work; the soft one does not.

## Three things worth separating

**1. `app.css:323` is the single highest-leverage line in the codebase.**

```css
.badge-vct, .badge-eis { @apply bg-pink-100 text-pink-800 font-medium; }
```

Non-palette pink in the **global** badge system, where `light-pink-*` exists.
One line; fixes every Venture Capital Trust and Enterprise Investment Scheme badge
app-wide. Left out of W-0045 only because `app.css` is shared ground and the
persona-tester may be mid-verifying Investment surfaces.

**2. The Risk module is a design decision, not a token swap — Azlan's call.**

The 276 occurrences are a deliberate 5-step sequential scale
(green → teal → blue → red) expressing risk level. **The Fynla palette has no
5-step sequential ramp.** Remapping it means either inventing one or accepting a
scale that reads worse, and neither is an agent's decision to take. This is the
largest cluster and the one that must not be swept mechanically.

**3. `app.css:543-547` are consequences, not causes.** `.bg-blue-50`,
`.bg-green-50`, `.bg-red-50`, `.bg-emerald-50` appear there as *selectors* in the
print block, preserving backgrounds on print. They become dead code once the
classes they target are gone — worth deleting in the same pass, not before.

## Acceptance

1. Azlan rules on the risk scale before any Risk-module file is touched.
2. `app.css:323` migrated to `light-pink-*`.
3. The remaining clusters migrated in agreed batches — in-app surfaces before
   public pages before admin, since that is the order users meet them.
4. **The safelist entries removed as the last step of each cluster**, so the
   mechanism cannot re-license what was just fixed. Removing them first breaks
   the pages before they are migrated.
5. Consider what replaces the safelist as the guard — a lint rule or a spec
   asserting no non-palette utility appears in `resources/js/`, on the model of
   the acronym test added in W-0080. Without one, this recurs.

- 2026-08-21 team-lead: **CLOSED AS DUPLICATE OF W-0048.** Same root cause (the Tailwind
  safelist), same 916-occurrence ledger, written in parallel before the messages crossed.
  W-0048 wins — raised first and carries the reviewer chain. The three things that had to
  survive (the `app.css:323` leverage point, the Risk ramp being Azlan's design decision
  rather than a token swap, and safelist entries being removed LAST per cluster) are
  recorded in W-0048's working notes.
  **CSJ has parked the whole palette workstream** until the functional board is clear —
  see W-0048. Do not work either item.
