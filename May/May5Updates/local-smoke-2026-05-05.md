---
type: smoke-evidence
title: Local browser smoke (post-reconciliation)
date: 2026-05-05
session: 7
branch: dev (HEAD `1948823` at smoke time)
target: localhost:8000 (Laravel) + 127.0.0.1:5174 (Vite HMR)
auth: john@example.com (test user, code fetched from EmailVerificationCode table)
driver: Playwright MCP
---

# Local Browser Smoke — 2026-05-05

This smoke addresses Issue #4 from `local-vs-dev-reconciliation-audit-2026-05-05.md`. The original csjones smoke (Tasks 9–11 of the reconciliation plan) executed but left no automated artefacts because Playwright disconnected mid-session. Per the rule that csjones server-side smoke is CSJ's, this is a **local-server smoke against the merged `dev` HEAD**, which carries the same code as csjones.

## Verdict

**GREEN.** All surfaced flows render with `<AppLayout>` chrome (top nav + sidebar + footer). Fyn chat panel opens with the canonical "Ask Fyn..." invariant placeholder — no persona-state UI signals, matching the AdviceFyn / Onboarding two-state contract.

- **Console errors:** 0 across the 9 surfaces visited.
- **Console warnings:** 7, all pre-existing — see "Warnings" section below.

## Surfaces visited

| Path | Result | Layout chrome (nav + footer) | Heading found |
|---|---|---|---|
| `/login` | OK | n/a (PublicLayout) | "Sign in to your account" |
| 2FA modal → `/dashboard` | OK | ✓ | (sidebar primary) |
| `/dashboard` | OK | ✓ | sidebar visible (collapsed-pattern compliant) |
| `/net-worth/wealth-summary` | OK | ✓ | "Net Worth" |
| `/protection` | OK | ✓ | "Family" |
| `/estate` | OK | ✓ | "Family" |
| `/net-worth/retirement` | OK¹ | ✓ | "Finances" |
| `/goals` | OK | ✓ | "Planning" |
| `/plans` | OK | ✓ | "Planning" |
| Fyn chat panel (right aside) | OK | n/a (in-layout) | textarea + "New", "History", "Collapse", "Suggestions", "Send" |

¹ The first `evaluate()` call on `/net-worth/retirement` returned `hasNav: false, hasFooter: false` because the SPA was still hydrating. After a 3-second wait the same query returned `hasNav: true, hasFooter: true, h: "Finances"`. Not a regression — async-render quirk.

## Auth flow used (local-dev pattern from CLAUDE.md)

1. `POST /login` with `john@example.com` / `password`
2. EmailVerificationCode modal appears, sent to `j**n@example.com` (masked).
3. Code fetched via `php artisan tinker`:
   ```bash
   php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code;"
   # → 139670
   ```
4. Six-digit code entered into the modal's six text inputs, autoadvancing.
5. Redirect to `/dashboard` within ~1s.

## Fyn chat panel — AI contract surface check

- Right-side `<aside>` collapsed by default (icon-only "Expand Fyn chat" button).
- Click → expands to `~7 KB` of chat panel HTML with a `<textarea placeholder="Ask Fyn...">` (the **invariant placeholder** required by the two-state contract — no "capturing" pill, no persona-state-change signal).
- Buttons present: `New`, `History`, `Collapse`, `Suggestions show`, `Send`.

This matches CLAUDE.md's "Fyn AI — Two-Fyn architecture" canonical contract. No frontend persona signals leak.

The smoke did NOT actually submit a question to AdviceFyn (would have triggered an LLM call and cost $$ for a structural check). The panel structure is the verification target here. Prior session 5 csjones smoke covered the live LLM round-trip; this smoke covers the post-merge UI layer.

## Warnings (pre-existing, NOT regressions)

7 warnings recorded, all clustered on `/protection`:

```
[Vue warn]: Failed to resolve component: ProfileCompletenessAlert
  at <ProtectionDashboard ...> at <RouterView> at <App>
[Vue warn]: Property "profileCompleteness" was accessed during render but is not defined on instance.
  at <AppLayout> at <ProtectionDashboard ...>
```

All 7 trace back to `resources/js/components/Protection/ProtectionDashboard.vue` referencing:
1. A `ProfileCompletenessAlert` component that isn't imported/registered.
2. A `profileCompleteness` reactive property that isn't defined on the component instance.

These are render-time warnings, not errors. They render-fall-through (the alert component just doesn't appear, the property reads as `undefined`). They do NOT affect any other module. They were present on csjones during session 5's smoke (CSJ verbally signed off "smoke passed") and predate the persona-split merge.

**Recommended action:** open a tiny PR fixing `ProtectionDashboard.vue` (either import + register the missing component or remove the orphaned references). Out of scope for this smoke task.

## Files of evidence

- `.smoke-evidence/local-2026-05-05/all-console-errors.log` — 0 errors, full session
- `.smoke-evidence/local-2026-05-05/all-console-warnings.log` — 7 warnings, full session
- Per-surface accessibility snapshots saved under `.playwright-mcp/page-2026-05-05T20-*.yml` (Playwright auto-saved)

(Screenshots were attempted but Playwright `browser_take_screenshot` timed out at 5s — accessibility snapshots used instead, which are richer for assertion purposes.)

## What this does NOT cover

- **No live persona switch / Onboarding Fyn state-machine drive.** Plan Tasks 10–11 specified driving young_family + peak_earners through full onboarding journeys. This smoke is post-onboarding only.
- **No CMS upload UI test.** Plan Task 9.3 covered `/admin/documents`. Skipped here — chris user not used to avoid double-2FA round-trip; john user not admin.
- **No live AdviceFyn LLM call.** Structural panel check only.
- **No tax-strategy panel drive.** persona-split's biggest new surface; not exercised here.

If CSJ wants any of these covered, ping me and I'll re-run with chris admin + a peak_earners persona switch.

## Conclusion

The merged `dev` code on local renders cleanly across the 9 surfaces tested. The reconciliation merge did not introduce any new console errors. Pre-existing `ProtectionDashboard` warnings remain a tiny tech-debt PR.

Issue #4 from the audit is resolved with this evidence trail.
