---
id: W-0081
title: The /m stylesheet hardcodes non-palette hex and invents two neutral shades
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
status: done
closed: 2026-08-29
severity: low
surfaces: [m]
source: found by design-lead during the W-0045 palette sweep, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Rule 11 permits palette tokens only and forbids hardcoded hex. `/m` does not use
Tailwind — it uses `:root` custom properties in `resources/mobile/style.css` — so
the W-0045 sweep for `blue-*` / `green-*` classes found nothing there. Looking at
the actual hex values does.

**Nine non-palette colours, all in `resources/mobile/style.css`:**

| Hex | Where | Note |
|---|---|---|
| `#6B7280` | lines 84, 94, 109, 168, 219 | Tailwind gray-500. The `/m` secondary-text colour. |
| `#E5E7EB` | lines 77, 78, 81, 166, 167 | Tailwind gray-200. Card borders, field borders, sheet handle. |
| `#C8CFDC` | 91, 180 | Not in the palette. Hero/net-worth sub-label. |
| `#E8ECF3` | 93, 182 | Not in the palette. Hero insight text. |
| `#F1F3F6` | 162, 184 | Not in the palette. Detail-row divider. |
| `#FBF8F4` | 88 | Not in the palette. Welcome gradient stop. |
| `#C42B54` | 82 | Not in the palette. `.m-err` — should be raspberry. |
| `#9CA3AF` | 42 | Declared as `--neutral-400`. Tailwind gray-400. |
| `#4B5563` | 44 | Declared as `--neutral-600`. Tailwind gray-600. |

The last two are the worst of it: they **invent shades of a palette token**. The
Fynla palette defines `neutral-500` (`#717171`) and nothing else in that ramp, so
`--neutral-400` and `--neutral-600` look like palette tokens at every call site
while being Tailwind greys wearing a Fynla name.

Everything else in the file is correct — the raspberry, horizon, spring, violet,
savannah and light-pink ramps are all byte-accurate palette values, and
`resources/mobile/tokens.js` mirrors five of them correctly for the confetti
generators.

## Why it was not fixed with W-0045

It is global `/m` chrome. `.m-card`, `.m-sub`, `.m-detail-key` and
`.m-section-label` are on essentially every mobile screen, so this moves the whole
`/m` surface at once — a different change with different risk from recolouring
four trust components, which is exactly the reasoning that kept W-0045 out of
W-0021.

## Acceptance

1. The seven literal hex values mapped to palette tokens, `.m-err` to raspberry.
2. `--neutral-400` and `--neutral-600` resolved — either removed in favour of
   `neutral-500`, or the palette formally gains those shades. **The second is a
   design decision and belongs to Azlan, not to an agent.**
3. `resources/mobile/tokens.js` re-checked against `style.css` afterwards — its
   docblock requires the two stay byte-identical.
4. Verified on `/m` per `verify-m`, across at least a card screen, a detail screen
   and the dashboard hero.

## Analysis 2026-08-25 — the finding holds, and it is wider than written

Re-verified against `resources/mobile/style.css` (220 lines) on branch
`fynla-bug-fixes`. All nine values are still present at the stated lines. Two
things the item did not anticipate, both of which change what "correct" means.

### 1. `--neutral-400` fails WCAG AA, and it is used as text

The item treats the two invented shades as a naming problem. One of them is an
accessibility defect:

| Colour | On white | AA 4.5:1 | Used |
|---|---|---|---|
| `--neutral-400` `#9CA3AF` | **2.54:1** | **FAIL** | 7 |
| `--neutral-500` `#717171` | 4.88:1 | pass | 69 |
| `--neutral-600` `#4B5563` | 7.56:1 | pass (and AAA) | 27 |

Five of the seven `--neutral-400` uses set `color:` on text —
`views/dashboard.css:595`, `:779` (`.md-rec.is-unlock .md-rec__lead`), `:1198`,
`:1587`, and `views/Subscription.vue:171` (unavailable plan features). The other
two are `stroke:` on chart geometry.

`fynlaDesignGuide.md` §Design Philosophy commits to "Accessibility First: Meets
WCAG 2.1 AA minimum". Text at 2.54:1 does not. **This is a live AA failure on `/m`
today**, independent of Rule 11, and it is the most serious thing in this item.

Removing `--neutral-400` in favour of `neutral-500` does not merely tidy a name —
it fixes that failure, because the replacement is darker.

Removing `--neutral-600` runs the other way: 27 call sites drop from 7.56:1 (AAA)
to 4.88:1 (AA). Still compliant, visibly lighter, and a deliberate choice rather
than a tidy-up.

### 2. Acceptance criterion 1 would break accessibility as written

The item says map `.m-err` to raspberry. Taken literally — `raspberry-500`, the
palette's error colour — that regresses an error message below AA:

| Candidate | On white | AA |
|---|---|---|
| `#C42B54` (current `.m-err`) | 5.50:1 | pass |
| `raspberry-500` `#E83E6D` | **3.93:1** | **FAIL** |
| `raspberry-600` `#DB2777` | 4.60:1 | pass |
| `raspberry-700` `#BE185D` | 6.04:1 | pass |

The design guide already anticipates this: its Error ramp assigns `error-500`
(`#E83E6D`) to *icons* and `error-600` (`#DB2777`) to *text and borders*. So the
correct mapping is `raspberry-600`, or `raspberry-700` to hold the current
contrast. Not `raspberry-500`.

### 3. The hero tints map cleanly

| Current | On navy `#1F2A44` | Proposed | On navy |
|---|---|---|---|
| `#C8CFDC` | 9.11:1 | `horizon-300` `#CBD5E1` | 9.60:1 |
| `#E8ECF3` | 12.03:1 | `horizon-200` `#E2E8F0` | 11.57:1 |

Both stay far above AA and are visually near-identical. No decision needed.

### 4. The stylesheet ignores its own tokens

The item counts nine non-palette values. The larger breach is that **essentially
every hex outside the `:root` block is hardcoded, including the correct ones** —
`#1F2A44` appears 10 times where `var(--horizon-500)` is defined at line 19,
`#E83E6D` 4 times, `#F7F6F4`, and `#fff` / `#ffffff` / `#FFFFFF` throughout while
`--white` exists and is used 74 times elsewhere.

Rule 11 forbids hardcoded hex, not merely non-palette hex. Fixing only the nine
leaves roughly thirty literals behind and the file still in breach — and leaves
the next sweep to rediscover it. Whether this item absorbs that or spawns a
sibling is a scoping call.

### 5. `tokens.js` is unaffected

`resources/mobile/tokens.js` mirrors five values — `raspberry500`, `spring300`,
`spring500`, `violet400`, `savannah500`. None is in scope of any change proposed
here, so the byte-identical requirement in its docblock stays satisfied. It must
still be re-checked afterwards, per acceptance 3.

### Parking

This item is `status: blocked`, parked 2026-08-21 under CSJ's palette decision on
W-0048, which is itself `blocked`: "no colour work of any kind until the
functional defect board is clear. Unpark with W-0048." Recorded here so the
analysis is not mistaken for authorisation to proceed.

- 2026-08-21 team-lead: **PARKED under CSJ's palette decision on W-0048** — no colour work
  of any kind until the functional defect board is clear. The finding stands as written
  (nine hardcoded non-palette hex values in the `/m` stylesheet, two of which —
  `--neutral-400` / `--neutral-600` — **invent shades of a palette token**, so they read
  as Fynla tokens at every call site while actually being Tailwind greys). Status moved
  `queued` → `blocked` so it is not picked up by a sweep. Unpark with W-0048.

## Done 2026-08-25 — unparked by Azlan, all four decisions taken

Azlan unparked this ahead of W-0048 and answered the three open calls. Recorded
here because the parking note was explicit and this overrides it.

| Decision | Answer |
|---|---|
| Unpark ahead of W-0048 | Yes, authorised by Azlan |
| The two invented neutrals | Drop `--neutral-400`, formalise `--neutral-600` in the palette |
| `.m-err` | `raspberry-600` `#DB2777`, **not** `raspberry-500` |
| Scope | Every hex in the file, not only the nine non-palette ones |

### What changed

**`resources/mobile/style.css`** — 39 hex literals below `:root` replaced with the
tokens the file already defined at the top. That includes the ~30 that were the
*correct* colour but hardcoded: `#1F2A44` appeared 10 times with
`var(--horizon-500)` defined at line 19. Rule 11 bans the literal, not merely the
wrong value.

| Was | Now | Note |
|---|---|---|
| `#6B7280` (Tailwind gray-500) | `var(--neutral-500)` | 4.83:1 to 4.88:1 |
| `#E5E7EB` (Tailwind gray-200) | `var(--horizon-200)` | borders |
| `#C8CFDC` | `var(--horizon-300)` | 9.11:1 to 9.60:1 on navy |
| `#E8ECF3` | `var(--horizon-200)` | 12.03:1 to 11.57:1 on navy |
| `#F1F3F6` | `var(--horizon-100)` | dividers |
| `#FBF8F4` | `var(--savannah-100)` | welcome gradient stop |
| `#C42B54` | `var(--raspberry-600)` | error text, 5.50:1 to 4.60:1, still AA |
| `#1F2A44` x10, `#E83E6D` x4, `#F7F6F4`, `#fff` x9 | their own tokens | were correct, but literal |

**`--neutral-400` removed**, with its 7 call sites remapped to `--neutral-500`:
`views/dashboard.css:595, 779, 1198, 1587, 1688, 1697` and
`views/Subscription.vue:171`. **This closes a live WCAG AA failure** — those sites
went from 2.54:1 to 4.88:1, and four of them were text.

**`fynlaDesignGuide.md`** gains `neutral-600` `#4B5563` in the Secondary Palette,
plus a note that the neutral ramp is deliberately these two shades and that no
lighter neutral may be introduced for text. Without this the token had no palette
authority and the next sweep would have flagged it again.

### Verified in the browser, not asserted

`npm run build:mobile`, then driven on `/m` as the student persona.

- **Every token resolves.** `--horizon-100/200/300/500`, `--raspberry-500/600`,
  `--neutral-500/600`, `--eggshell-500`, `--savannah-100`, `--white` all return
  their expected value from `getComputedStyle`. `--neutral-400` returns nothing,
  as intended.
- **Nothing renders in a removed colour.** Swept all 388 elements on the dashboard
  for the old values: `#9CA3AF` zero, `#E5E7EB` zero, `#6B7280` zero. 80 elements
  now render `#717171`.
- **Dashboard hero** — level wheel, milestone card, LEVEL UP band, protection
  nudge all correct. `body` background resolves `#F7F6F4`.
- **Detail screen** (`/m/app/net-worth`) — hero `#1F2A44`, sub-label `#CBD5E1`,
  insight `#E2E8F0`, card border `#E2E8F0`, section label `#717171`. Each is the
  intended token.
- **Card-screen rules** confirmed by instantiating them live: `.m-err` `#DB2777`,
  `.m-detail-key` `#717171`, `.m-back` `#E83E6D`, `.m-detail-row` border
  `#F1F5F9`, `.m-sub` `#717171`, `.m-btn` background `#E83E6D`.
- **`design-lint` clean** on the changed files — using the repaired hook, which
  actually runs now (W-0483).
- Screenshots: `.playwright-mcp/w0081/`.

### tokens.js — acceptance 3

Re-checked. `raspberry500`, `spring300`, `spring500`, `violet400`, `savannah500`
all still byte-identical to `style.css`. None was in scope, so its docblock
contract holds without edit.

### Gaps

- **Only the student persona was driven.** Other personas share this chrome, so
  the risk is low, but they were not each opened.
- **iOS not checked.** `ios-native/` is a separate SwiftUI target and does not
  consume this stylesheet, so no parity obligation — stated rather than assumed.
- One console error on `/m`, pre-existing and unrelated: `403` on
  `POST /api/ai-chat/onboarding/start` in a preview session.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Phailanx
- **Evidence:** merged in #718; commit `1126f32fb` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
