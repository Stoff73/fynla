# R-15 — Cap-lift test: GREEN on outcome 1, the only acceptable one

**When:** 2026-08-21 17:55–18:10 · **Surface:** desktop web, `localhost:8000`
**Driver:** Playwright MCP, real pointer clicks, isolated context [2].
**Subject:** `users.id 31` — `pt.throwaway.cap+0821@example.com` (Tomas Weber).
Priya (20) and the Jones household (16/17) untouched.

---

## Result

**The cap lifted live. No reload, no re-login, no navigation.**

| Step | Expected to distinguish | Result |
|---|---|---|
| **1. Capped action, no reload, no re-login** | lifts live — **the only acceptable outcome for a customer who has just paid** | **PASS** |
| 2. Reload without re-authenticating | lifts only on reload | **not needed** |
| 3. Re-login | needs re-authentication — a commercial defect | **not needed** |

The test terminated at the best outcome, so steps 2 and 3 were not run. Recording that
explicitly: they were **skipped because the test passed at step 1**, not overlooked.

### The evidence, before and after

Identical action — clicking **Add Life Event** at the cap — in the same held browser
session, with nothing touched in between but team-lead's server-side provisioning:

| | Baseline (free) | After provisioning (no reload) |
|---|---|---|
| Button | `visible: true, enabled: true` | `visible: true, enabled: true` |
| Real pointer click | **`formOpen: false`** | **`formOpen: true`** |
| Upgrade affordance | **present** | **absent** |
| "Upgrade Now" header chip | present | **absent** |

**And the save genuinely went through, which is the part that proves it:**

```
POST /api/life-events → 201   "Life event created successfully."
UI: "2 events"                (free count_cap was 1)
```

Opening the form is necessary but not sufficient — the server could still have rejected
the write. It did not.

### Database confirmation

```
user 31   tier=premium   TierResolver::resolve() → premium
TeaserGate mode(life_events) = full     allows() = true      (was limited / false)

life_events:
  id=83  Parents Estate  £120,000  2035-06-01  created 14:01:00   <- pre-provisioning
  id=84  Annual Bonus    £35,000   2027-04-01  created 14:09:46   <- POST-provisioning, no reload
total: 2   (free cap was 1)
```

`id=84` is the whole result: a row that the free cap forbade, written after
provisioning, in a session that was never reloaded or re-authenticated.

### No staleness to diagnose

team-lead's warning about memoised entitlement state did not bite. Because the cap
lifted at step 1, there was no "still capped" result to attribute, and no need to
separate client-held capability state from a server-side per-request memo. Worth noting
the discipline was ready rather than unused by luck: had step 1 failed, the next action
was to locate *where* the staleness lived before calling it.

---

## Protocol integrity

This retry was run to the letter, after the first attempt was voided:

1. I registered `users.id 31` through the real registration flow and left it on free.
2. I captured the baseline and screenshotted it.
3. **I stopped.** No navigation, no reload, no click, no authentication.
4. I told team-lead the session was held.
5. team-lead provisioned server-side and verified in a **clean process** — deliberately
   not reading `TierResolver` in the process that wrote it, which is what produced a
   phantom `free` earlier in the day.
6. team-lead confirmed.
7. I acted, without touching anything first.

The first attempt failed on exactly the two points this one controlled: premium landed
before the baseline, and my own `page.goto()` destroyed the in-session question. Either
alone would have voided it.

---

## Not done, and why

- **Steps 2 and 3 of the sequence** — unnecessary; see above.
- **Only one capability was tested.** `life_event` lifted live; I have **not** shown that
  every capped capability behaves the same way on upgrade. `property`, `investment`,
  `goal`, `savings_account` and `pension_account` are all `limited` on free and
  **untested for lift**.
- **The `/m` and iOS equivalents are untested** — a cap that lifts live on web could
  still be stale on a different client holding its own entitlement state.

---

## Noticed — raised as W-0054

Establishing the baseline surfaced something independent of the lift result: **two
capped capabilities, two entirely different gating philosophies.**

- `life_event` blocks **before entry** — the form never opens, an Upgrade affordance
  appears. Correct.
- `expenditure_detailed` (W-0011) blocks **after submit** — the form opens, accepts
  every field, then 403s silently and loses the work.

The life-event path is the pattern to converge on. Raised as **W-0054** so the
inconsistency is recorded as a general problem rather than living inside W-0011 as a
single-module bug.

---

## Context position

Roughly **715k**. Handover R-14 already written and updated with this result.
