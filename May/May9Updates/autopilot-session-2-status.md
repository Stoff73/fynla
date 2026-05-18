# Autopilot session 2 status — 2026-05-09

**Time:** Started ~07:25 BST (cron-fired). Reached this checkpoint ~09:00 BST.
**Branch:** `dev` at `31b4813` (latest origin/dev). Three feature branches pushed below.
**Tree:** standing carry-over only (FCA/, fyn/, campaigns/, personas/, prompts/, tools/, deployFynFix.md, etc.).

---

## What shipped

### 1. Deferred deploy-gate memory file

[`feedback_deploy_gate_csjones_before_admin_merge.md`](file:///Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_deploy_gate_csjones_before_admin_merge.md) — captures the rule from PR #265 work (deferred sessions 14, 15, 16 of 8 May): for solo-author PRs needing csjones verification, deploy the FEATURE BRANCH to csjones BEFORE admin-merge, never after. csjones is a real git checkout, can `git checkout` any remote branch. Indexed in `MEMORY.md`.

### 2. PR #266 — `fix(audit): null user_id when actor is hard-deleted to prevent FK 500s`

https://github.com/Stoff73/fynla/pull/266

CSJTODO **Defect 1**. `app/Models/AuditLog.php:137` did `auth()->id() ?? null` without verifying the user exists. When a user is force-purged with a stale session, the next authenticated POST 500s with `audit_logs_user_id_foreign`. Fix: `User::withTrashed()->whereKey($id)->exists()` gate; falls back to `null`. New `tests/Unit/Models/AuditLogTest.php` reproduces the prod FK violation in 2 of 6 cases pre-fix; all 6 green post-fix. Wider audit/auth/account/advisor/api suites also green.

**Confirmed on prod laravel.log:** `2026-05-08 13:54:11 audit_logs_user_id_foreign` — user 611 had been force-purged, next subscription-create 500'd. PR #266 closes this loop.

### 3. PR #267 — `fix(retention): pace SMTP sends to 5/s to stay under SiteGround 10/s cap`

https://github.com/Stoff73/fynla/pull/267

CSJTODO `data-retention:send-warnings` SMTP rate-limit fix. SiteGround relay caps at 10 msg/s and returns 451 above; current `SendDataRetentionWarnings::handle` had no pacing. Fix: `Sleep::usleep(200_000)` (5/s) at end of foreach, after the try/catch so failed sends still pace. New `tests/Unit/Console/Commands/SendDataRetentionWarningsTest.php` (3 cases) uses `Sleep::fake()` for instant CI.

**Confirmed on prod laravel.log:** the 451 errors fired daily at 09:00 on 2026-05-03, 05, 06, 07, 08 with the exact "received more than 10.X messages for 1s" wording.

### 4. PR #268 — `fix(mail): pace daily cron mail loops to 5/s to share SMTP cap fairly`

https://github.com/Stoff73/fynla/pull/268

Generalisation of #267 to the three sibling cron commands at the same SMTP cap:

| Command | Schedule | Driver | Why included |
|---|---|---|---|
| `subscriptions:send-renewal-reminders` | 09:00 | `Mail::send` | Same 09:00 batch as #267 |
| `trials:send-reminders` | 09:00 | `Mail::send` | Same 09:00 batch — three loops × 10 msg/s = certain to overflow |
| `accounts:send-deletion-reminders` | 00:20 | `Mail::queue` | `QUEUE_CONNECTION=sync` on prod → resolves to a synchronous send → same risk |

Same pattern, three new tests, all green. 95-test wider suite still green.

### 5. Bug 2 dashboard retention investigation note

[`May/May9Updates/dashboard-retention-bug-investigation.md`](dashboard-retention-bug-investigation.md) — full investigation of the CSJTODO "dashboard reads retention-flagged data after Delete My Data" bug. **Blocked on CSJ design call.** The fix needs a new `users.data_erasure_requested_at` column (no canonical column exists for the data-only erasure path); column name, re-entry semantics, and scope (Profile Completeness only vs all dashboard widgets) all need a CSJ steer. Note proposes a specific solution + lists 5 open questions so CSJ can ack/ammend in a few minutes.

**Did NOT ship a guess.** Per autopilot rules, a column-name choice with FCA implications is not a "I could pick either" decision.

---

## New bug surfaced (separate from anything CSJTODO)

**`investment_accounts.country` NOT NULL crash on prod** — 2026-05-07 12:15 fired twice for user 444:

```
SQLSTATE[23000] Integrity constraint violation: 1048 Column 'country' cannot be null
(SQL: insert into `investment_accounts` ... values (..., NULL, ...))
```

The DB column is `varchar(255) NOT NULL DEFAULT 'United Kingdom'`. The form requests (`StoreInvestmentAccountRequest`, `UpdateInvestmentAccountRequest`) validate it as `nullable|string|max:255` — when the request body has `country: null`, it passes validation, gets passed through `InvestmentAccount::create($validated)`, and the DB rejects the explicit NULL.

**Proposed fix** (NOT implemented — out of scope for today's PRs):

Add `protected function prepareForValidation(): void` to both request classes that drops `country` from the input when it's null/empty (so the DB default kicks in). ~10-line change, plus test. CSJ to confirm whether this should be a discrete PR or rolled into a wider "form-default-handling" sweep.

Reproducibility: probably user 444 created an investment account on 2026-05-07 with a form that didn't populate country. Since then no recurrences in the log — possibly a frontend regression that was self-corrected, or a one-off data-import edge case. Worth leaving in CSJTODO until traced to its source.

---

## Other prod-log signals reviewed (no action)

- **`MissingAppKeyException` events on 2026-04-30, 2026-05-06** — config/cache desync after deploy. Not active. No action.
- **`'forge'@'localhost' Access denied` on 2026-05-06 07:24** — single occurrence, stale config-cache from a deploy. Not active.
- **`Mews\Purifier\PurifierServiceProvider` not found 2026-05-06 14:24 (×2)** — stale cached ServiceProvider list. Not active.
- **`UserSession.php:129 Undefined property TransientToken::$id` 2026-04-30** — single occurrence. Defensive null-guard worth flagging in CSJTODO but low priority.
- **`data_retention_email_log` unknown column 'user_id' 2026-05-07** — already excluded by `RetentionPurgeService` per existing comment. Not active.

---

## What CSJ has waiting

1. **Three open PRs** to review/merge (#266, #267, #268). All defensive backend fixes with comprehensive Pest tests; backend-only, no UI. Per the deploy-gate memory: when ready to merge, deploy the feature branch to csjones first, browser-verify, then admin-merge. For these specific PRs the "browser-verify" step is largely formality — the test coverage proves the behaviour and there's no UX surface that a manual click would catch. CSJ to decide whether the gate's csjones step is needed or whether the Pest evidence is enough.

2. **Bug 2 design call** (5 questions in [`dashboard-retention-bug-investigation.md`](dashboard-retention-bug-investigation.md)). Once answered, ~3h to ship the dashboard retention-flag fix.

3. **Rollback artefacts cleanup** — `~/www/fynla.org/public_html/public/build.old/` and `~/tmp/fynla-deploy-*.tar.gz`. The 24h prod-stability window should hit ~21:30 BST today. Autopilot will pick this up later if a continuation lands after that time.

4. **Optional prod probes** (need CSJ MFA on fynla.org):
   - `delegate_to_capture` write-intent flow with grok-4.3
   - Deeper net-worth phrasings ("Combined wealth", "How much am I worth?", "Show me my net worth")

---

## What I did NOT do

- No admin-merges (CSJ has not approved any of the three PRs — staying within the gate)
- No prod browser tests (need CSJ MFA — not at the keyboard)
- No code change for Bug 2 (needs CSJ design call)
- No code change for the new `investment_accounts.country` bug (out of scope, surfaced for triage)
- No stale-branch deletion (`branch -D` is a destructive op — CSJ to delete `temperature-zero-everywhere-v2` and the other 5 fully-merged branches when convenient)
- No prod rollback-artefact cleanup yet (24h window not closed)

---

## Branches

| Branch | State | Tip |
|---|---|---|
| `fix/audit-log-fk-deleted-user` | Pushed, PR #266 open | `c4bf722` |
| `fix/data-retention-smtp-rate-limit` | Pushed, PR #267 open | `f337645` |
| `fix/throttle-cron-mail-loops` | Pushed, PR #268 open | `bd95eaf` |
| `dev` | At `31b4813` (in sync with origin/dev) | — |
| `main` | At `f8f918c` (handover docs ahead of dev — established pattern) | — |

---

## Continuation strategy

The bounded autonomous-work backlog is empty. Remaining items are CSJ-gated. Per autopilot rules I'll continue light-touch investigation work (already running out) and let the chain naturally hit the tripwire OR the 23:00 EOD cutoff. If a continuation fires after ~21:30 BST it can do the rollback-artefacts cleanup; otherwise CSJ wraps when convenient.
