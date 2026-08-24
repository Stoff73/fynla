# W-0177 — build-lead (`cycle1-tax`) → quality-lead

## Done

`income_needs_update` is now a flag-style requirement: it exists on the panel only while
raised.

- `ModuleDataRequirementsService::FLAG_REQUIREMENTS`
  (`app/Services/UserProfile/ModuleDataRequirementsService.php:602-614`) names the class.
- The field loop skips a satisfied one (`:641-646`).

One constant covers both modules that declare the flag — `profile` and `protection`.

Measured against the live persona (flag flipped **in memory only and never saved** —
`isDirty()` true, no `save()`, nothing written to user 16):

| Flag | Panel |
|---|---|
| down | filled 8, total 8, **100%**, missing empty, no contradictory entry |
| up | filled 8, total 9, "Income needs updating" under OUTSTANDING |

Tests: `tests/Unit/Services/UserProfile/FlagRequirementCompletionTest.php` — 6 passing,
including the `protection` module and an ordinary missing field still reporting.

## Not done, and why

- **No browser verification.** By instruction.
- **`isFieldFilled()`'s inversion for this key is left in place** (`:721-724`). It is what
  decides whether the flag is raised, and it is correct — the bug was in what the caller
  did with a "filled" flag, not in the predicate.
- **No commit, no PR, no deploy.** By instruction.

## What you need that isn't obvious from the artefacts

**The count changes, and that is the fix, not a side effect.** A complete `profile` reads
**8 of 8 / 100%** where it previously read 9 of 9. Anything asserting a total of 9, or a
completion percentage computed against 9, will move. I found nothing doing so; if a
dashboard tile or a gamification rule counts these, it needs re-reading.

**The panel is reached from more routes than the one the item names.**
`resources/js/utils/moduleMap.js` maps `/valuable-info`, `/settings` **and** `/profile` to
the `profile` module, so the contradiction was visible on all three, and the fix lands on
all three at once. Worth checking more than the income page.

**`/protection` carried the identical contradiction** and was not in the item's repro.
It is fixed by the same constant. Please verify it too — a per-module patch would have
left it standing, which is why it is a constant rather than a condition on `profile`.

**How to raise the flag for verification.** `users.income_needs_update` is a boolean set
when employment status changes; `UpdateIncomeOccupationRequest` accepts it directly. Do
not set it on users 16 or 17 — a tester holds that household. Use your own fixture.

## Assumptions I made

- **That a lowered flag should vanish rather than be reworded.** The alternative was a
  neutral label such as "Your income is up to date", which reads correctly under Completed
  but wrongly under Outstanding. Vanishing is right because the flag is not a data
  requirement — there is no state in which the user "supplies" it. **If CSJ wants credit
  visible for having refreshed income, that is a product decision and would need the
  reworded-label approach instead.**
- **That `income_needs_update` is the only flag-shaped requirement today.** I read every
  entry in `MODULE_REQUIREMENTS`; the rest are fields or relationships a user genuinely
  supplies. The constant is a list so a second one costs one line.
- **That the completion percentage is not consumed anywhere that expects a fixed
  denominator.** Grepped `completion_percentage` and found only the panel; not exhaustive
  across the mobile bundles, which do not render this panel at all.

## Surfaces covered / not covered

- **web** — covered, the only surface with the panel.
- **`/m`** — **no counterpart.** Grepped `resources/mobile/` for `info-guide`, `infoGuide`
  and `ModuleStatusBar`: nothing.
- **iOS** — **no counterpart.** Same grep across `ios-native/Fynla/`: nothing.

Stated rather than assumed, per Rule 19. The fix is server-side, so any future surface
inherits it.
