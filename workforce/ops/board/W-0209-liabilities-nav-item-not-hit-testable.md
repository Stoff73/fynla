---
id: W-0209
title: The "Liabilities" side-navigation item is not hit-testable — a section header receives the pointer at the link's own centre, so a real click never reaches it
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: design-lead
status: closed_invalid
severity: high
surfaces: [web]
created: 2026-08-22T01:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: []
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 3 journey re-walk, local, `david.jones@example.com`, read-only.
**Surface:** `AppNavbar` side navigation, the **Liabilities** item under FINANCES.

Found only because this run uses **real pointer clicks**. A dispatched DOM click fires the
handler and reports success, so this class of defect is invisible to any test that
dispatches events rather than clicking.

### Expected

Every side-navigation item is clickable at its own centre.

### Actual

A real Playwright click on the Liabilities link **times out after 30 seconds**, retrying
throughout, with the actionability log reporting the element as "visible, enabled and
stable" while other elements intercept the pointer:

```
- <div class="flex-1 overflow-y-auto py-2 scrollbar-hide"> intercepts pointer events
- <button class="flex items-center justify-between w-full px-4 pt-3 pb-1 group"> intercepts pointer events
- <span class="... uppercase tracking-wider ...">Personal Affairs</span> intercepts pointer events
```

Confirmed independently in the DOM — the link's own centre point does not hit the link:

```js
a = document.querySelector('a[href="/net-worth/liabilities"]')
// rect: x 8, y 265, w 207, h 36  (viewport height 1100 — well in view)
document.elementFromPoint(273, 283)
//  → SPAN.text-[11px] font-semibold uppercase tracking-wider …   ("Personal Affairs")
//  a === top || a.contains(top)  →  false
```

So the topmost element at the middle of the "Liabilities" row is the **"Personal
Affairs" section header**, several rows below it. A user clicking Liabilities either hits
nothing or collapses a different section.

**The destination itself is fine.** `/net-worth/liabilities` loads correctly and shows the
mortgages with ownership shares and the correct explanatory note that mortgages are
managed in Property.

### Impact

A navigation item that cannot be clicked makes a whole module unreachable by its intended
route. Liabilities sits among Investments, Retirement, Property, Personal Valuables, Risk
Profile and Business — all real destinations — so there is nothing to tell the user the
item is not simply broken.

**It may not be limited to this item.** The interceptors are the scrolling container and a
section header, which suggests a stacking or hit-area problem in the nav rather than
anything specific to Liabilities. **Check every item before closing.**

### Repro

1. `david.jones@example.com` → `/dashboard`, dismiss the level-up modal, wait for hydration.
2. Click **Liabilities** in the side navigation with a real pointer click.
3. It never activates. Run the `elementFromPoint` probe above.
4. Navigate directly to `/net-worth/liabilities` — the page itself works.

### Acceptance

1. Every side-navigation item is hit-testable at its own centre —
   `document.elementFromPoint(centre)` returns the link or a descendant of it.
2. Verified by **real pointer clicks**, not dispatched events, on every nav item in both
   expanded and collapsed states.
3. Section headers and the scroll container do not extend their hit area over sibling rows.

### Notes

- `/liabilities` (without the `/net-worth` prefix) redirects to `/dashboard`. That is the
  single-page-application catch-all behaving correctly for an unknown route, **not** a
  defect — the real href is `/net-worth/liabilities`. Recorded so nobody re-finds it.

- 2026-08-23 — **CLOSED INVALID. The link is inside a COLLAPSED accordion section, and
  nothing is intercepting it.** Reproduced the report exactly, then found the cause.

  On `/dashboard` as `david.jones@example.com`, with **FINANCES collapsed** (its default):

  | Ancestor | display | visibility | height |
  |---|---|---|---|
  | `a[href="/net-worth/liabilities"]` | flex | **hidden** | 36px |
  | `div.flex.flex-col` | flex | **hidden** | 252px |
  | `div.overflow-hidden` | block | **hidden** | **0px** |
  | `div.grid.transition-[grid-template-rows,visibility]` | grid | **hidden** | **0px** |

  The section collapses by animating `grid-template-rows` to zero with `overflow:hidden`.
  The inner flex column still lays out at full height INSIDE that clipped zero-height
  wrapper, so **the link keeps a 207×36 bounding rect while being `visibility: hidden`.**

- 2026-08-23 — **Expanding the section makes it hit its own centre.** Clicked the FINANCES
  header, then re-ran the identical hit-test: `visibility: visible`, and
  `elementFromPoint` at the link's centre returns `SPAN.ml-3…` reading **"Liabilities"** —
  `hits: true`, where it was `false` before. **A real Playwright click then navigated to
  `/net-worth/liabilities` immediately** — no timeout, no interception.

- 2026-08-23 — **Why the original report reads so convincingly, and is still wrong.** This
  is a tooling artefact worth remembering rather than a mistake to hold against the
  reporter. Playwright's actionability check reports "visible, enabled and stable" from the
  **bounding rect**, which is non-zero here; it then hit-tests and reports whatever it
  finds as "intercepting pointer events". Those elements are not covering the link — they
  are simply what is painted at coordinates the hidden link merely *claims*. The
  "Personal Affairs" header named in the report sits where it does because the collapsed
  section occupies no space, so the rows below slide up under the phantom rect.

- 2026-08-23 — **The generalisable trap, for the next person testing this nav.**
  `getBoundingClientRect()` is NOT a visibility test in this component. A nav item in a
  collapsed section reports a full-size rect and fails every click. **Expand the section
  first, or assert `getComputedStyle(el).visibility === "visible"` before treating a rect
  as a target.** Every item under CASH MANAGEMENT, FINANCES, PERSONAL AFFAIRS and PLANNING
  behaves this way — so this would otherwise be re-filed once per section.
