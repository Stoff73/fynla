---
type: handover
mode: context-clear
date: 2026-05-08
session: 15
branch: main
trigger: context-watch tripwire fired at ~204k tokens (>97.5%) RIGHT after CSJ provided prod MFA `222750`. Wrap forced before MFA was entered.
previous_session: 2026-05-08 session 14 (PR #265 opened + dev-verified)
---

# Context Clear Handover — 2026-05-08, Session 15

## Immediate state

**PR #265 is MERGED → main is at `1939a89` → fynla.org production has been deployed (files extracted, optimize cycle clean) → browser test was MID-LOGIN when the tripwire fired.** Playwright tab on `https://fynla.org/login` with email + password already submitted, MFA screen up, and **CSJ provided code `222750` in the user message that triggered the tripwire — that code has NOT been entered**. MFA codes typically expire in ~5 minutes from when they were sent, so the next session will likely need a fresh code from CSJ.

## The thread

1. **Session-start auto-continued** from session 14's "Pick up from here" — option (a): smoke current prod first to document the bug. Logged into `fynla.org` as `chris@fynla.org` with MFA `884472`. Ran 3 queries against prod-pre-deploy:
   - **Q1 "What is my net worth?"** → Fyn replied **£586,750** (canonical = £598,250, off by £11,500). Different shape from session 11's £260k hallucination — Fyn correctly itemised liquid + pension + property + business but missed Rolex chattel (£15k) and credit-card debt (£3,500).
   - **Q2 "show me my protection plans"** → frontend `chatNavigationRouter.js` intercepted, navigated to `/plans/protection`. Page rendered fully. GREEN.
   - **Q3 "how do I optimise my retirement"** → Fyn produced a thoughtful chat reply with sensible recommendations (max AA, get State Pension forecast, review Scottish Widows). Soft mismatch: Fyn projected capital ~£758k vs dashboard £847,873 — likely because Fyn doesn't fold in State Pension. Not a hard bug.
2. **F6 cleared** — fetched `https://docs.x.ai/docs/models`. `grok-4-1-fast-reasoning` (the model prod was running) is **NOT** in the 2026-05-15 retirement list. No retirement urgency.
3. **CSJ asked to investigate the BTL `£1,750` mortgage share** as a possible joint-asset bug. Investigated with tinker:
   - `ChrisUserSeeder.php:181` seeds BTL `outstanding_balance = 3,500.00` with `ownership_percentage = 50.00`.
   - 50% of £3,500 = £1,750. **The £1,750 is mathematically correct per seed data — NOT a bug.**
   - Reconciled the £11,500 net-worth discrepancy exactly: missing Rolex chattel (-£15k) + missing credit-card debt liability (+£3.5k) = -£11,500.
   - Both entity types ARE supported by `list_records` (`CoordinatingAgent.php:1542` chattel, `:1547` estate_liability) — the LLM just doesn't know to fan out to every relevant entity type when hand-rolling net worth.
   - **PR #265's classifier fix solves this** by routing net-worth → HOLISTIC_HEALTH so the canonical NetWorthService figure is included in `financial_context` directly. LLM echoes it instead of re-deriving.
4. **CSJ corrected my mental model of prod.** I wrote "prod is on grok-4.3" but actually prod at `3c47e2a` was still on `grok-4-1-fast-reasoning` — the grok-4.3 swap (PR #258) and reasoning_effort=none (PR #259) and temperature=0 (PR #261) all landed on `main` after `3c47e2a` but were never deployed. CSJ said "we need to deploy".
5. **CSJ said "merge and go".** PR #265 OPEN BLOCKED. I polled in background; CSJ merged in GitHub UI; said "merged, go".
6. **Deploy executed.** `git checkout main && git pull` → main now at `1939a89`. `./deploy/fynla-org/build.sh` → `public/build/` rebuilt (8.9M, 334 entries). Tarred up 11 PHP files + bundle. `scp` via `~/.ssh/production` to `~/tmp/` on prod. `ssh_exec` extracted both tarballs to `~/www/fynla.org/public_html/` (kept old build at `public/build.old` for rollback). Verified `config/services.php` line 42-44 has `grok-4.3`; `ConversationSummariser.php:130-131` has `max_completion_tokens` + `temperature=0`. Ran `php artisan migrate --force` (Nothing to migrate), then `cache:clear && config:clear && view:clear && route:clear && composer dump-autoload -o && php artisan optimize` — all clean.
7. **Browser re-test attempt.** Navigated Playwright to `https://fynla.org/dashboard` → redirected to `/login` (session expired). Filled email + password, clicked Sign in. MFA screen came up. **Tripwire fired** in the same user message as CSJ's MFA code (`222750`). The code is NOT yet entered.

## Files touched this session

- **No code edits this session.** All deploy work was on existing main HEAD.
- **Local commits since session 14**: 1 (`1939a89` Merge PR #265 — fast-forward from `git pull origin main`).
- **Remote state changes**:
  - `main`: `2edeb27` → `1939a89` (PR #265 merge, by CSJ)
  - `dev`: unchanged at `462dc90` (already merged to main via #265)
  - PR #265: MERGED.
  - **Production server state**: 11 PHP files replaced + entire `public/build/` directory swapped. Old build at `~/www/fynla.org/public_html/public/build.old/` (rollback path).

## WIP commit

- **None.** Working tree clean except the standing untracked carry-over (FCA/, campaigns/, fyn/, personas/, prompts/, tools/, Fynla-Narrative-Memo-Template.docx, FCA-Supercharged-Sandbox-Application-Draft.md, FCAsuperchargeApp.md, May/May1Updates/deployFynFix.md) — same standing decision not to commit as sessions 2–14.
- Last commit: `1939a89 Merge pull request #265 from Stoff73/dev` (the merge CSJ did, pushed by GitHub).

## Open decisions (auto-resume defaults)

1. **The MFA code `222750` from CSJ's last message — try it, but it may have expired** (typical 5-minute TTL; ~3 minutes elapsed by the time the next session starts after `/clear` and re-bootstrap). **Auto-resume default**: ask CSJ for a fresh code. If CSJ doesn't reply within a few seconds, attempt `222750` first (low cost — failure just shows "code expired" and re-prompts).
2. **The 3 queries to re-run on POST-DEPLOY prod**:
   - Q1: "What is my net worth?" — **must return £598,250** (canonical). If still £586,750, the deploy didn't take effect. If it returns the canonical figure, **the bug is fixed**.
   - Q2: "show me my protection plans" — should still nav to `/plans/protection` (frontend interception). Re-confirm page renders.
   - Q3: "how do I optimise my retirement" — re-confirm sensible reply. Optionally compare to Q3 from pre-deploy to see if grok-4.3 vs grok-4-1-fast-reasoning behaves differently.
3. **`public/build.old/` cleanup** — left in place for rollback. Once post-deploy testing confirms green, delete: `mcp__ssh-fynla__ssh_exec` → `rm -rf ~/www/fynla.org/public_html/public/build.old && rm ~/tmp/fynla-deploy-*.tar.gz`. **Auto-resume default: leave it for now until prod is confirmed green.**
4. **Tail laravel.log for 10–15 min post-deploy** per the deploy guide. **Auto-resume default: tail in parallel with browser test** via `mcp__ssh-fynla__ssh_read_file` with `lines: 50` after each query.
5. **Vault-sync STILL deferred** for sessions 6–15 of May 8 (10 sessions). Batch via Haiku 4.5 subagent on next eod wrap.

## Pick up from here (auto-continue contract)

**STEP 0 — Resume browser test on `https://fynla.org/login` MFA screen.** Try MFA `222750` first (it's a few minutes old — likely still valid). If it fails, ask CSJ for a fresh code.

**STEP 1 — After login lands on /dashboard**:
- Verify Net Worth card still shows canonical **£598,250 / Assets £803,500 / Liabilities £205,250**. If anything is different, the deploy may have broken something — investigate.

**STEP 2 — Run the 3 chat queries** (open Fyn chat panel on dashboard):
- Q1: "What is my net worth?" — **PASS criterion: reply must contain `£598,250`**. The classifier fix routes to HOLISTIC_HEALTH so financial_context returns the canonical figure.
- Q2: "show me my protection plans" — PASS: URL navigates to `/plans/protection`, page renders.
- Q3: "how do I optimise my retirement" — PASS: sensible chat reply. Note: now running on grok-4.3 + reasoning_effort=none + temperature=0, so output should be more deterministic than yesterday's grok-4-1-fast-reasoning.

**STEP 3 — Tail laravel.log** for any 500s or stack traces:
```
mcp__ssh-fynla__ssh_read_file path: storage/logs/laravel.log lines: 100
```
Re-tail after each query. If any errors → diagnose → may require rollback to `public/build.old/`.

**STEP 4 — Once green**, optionally:
- Test extra net-worth phrasings ("Combined wealth", "How much am I worth?", "Show me my net worth") — session 14 csjones-verified all 5 GREEN, prod should match.
- Delete `public/build.old/` and `~/tmp/fynla-deploy-*.tar.gz`.
- Update `CSJTODO.md` with the deploy outcome.
- Write feedback memory along the lines of *"Deploy gate: branch-to-csjones via git fetch+checkout BEFORE admin-merge, never after. The csjones environment is the dev test surface and must be hit with the actual feature branch, not the post-merge dev branch."* (deferred from session 14).
- Trigger vault-sync for sessions 6–15 if CSJ wraps day.

## What the next Claude needs to know

- **Production now runs grok-4.3 with reasoning_effort=none + temperature=0.** This is THE FIRST TIME grok-4.3 has run in production. Any model-behaviour weirdness (different tone, different brevity, different math) is expected from the model swap, NOT a regression. Compare to csjones session 14 verification (all 5 GREEN with £598,250) for baseline.
- **Production HEAD is `1939a89`** (PR #265 merge). Brings together: classifier fix + max_completion_tokens + temperature=0 + grok-4.3 swap + reasoning_effort=none.
- **Rollback path**: `mcp__ssh-fynla__ssh_exec` → `cd ~/www/fynla.org/public_html && rm -rf public/build && mv public/build.old public/build && git checkout 3c47e2a -- app/ config/ 2>/dev/null` — but DON'T do this without CSJ's go-ahead. The deploy needs to be tested first.
- **The 11 deployed PHP files** were extracted directly from a tarball; mtimes show today's date but content is from local main HEAD `1939a89`. Verified `grok-4.3` and `max_completion_tokens` are in place.
- **Old `public/build.old/` directory exists on prod** — do NOT confuse with active `public/build/`. Apache serves `public/build/`. The .old is rollback only.
- **CSJ's MFA code `222750`** was provided right before the tripwire. May still be valid. Try it; fall back to asking for a fresh one.
- **Bug analysis from this session** (preserved here so it's not lost):
  - £11,500 net-worth gap on prod = missing Rolex chattel (-£15k) + missing credit-card debt (+£3.5k).
  - The £1,750 BTL mortgage share is CORRECT (50% of seeded £3,500). NOT a bug.
  - Latent issue: when LLM calls `list_records('chattel')`, Chattel #233 has `name=null, description=null` — only `current_value=£15,000` is populated. Cosmetic seed-data issue.
- **Standing untracked carry-over** (FCA/, campaigns/, fyn/, personas/, prompts/, tools/, Fynla-Narrative-Memo-Template.docx, etc.) deliberately NOT committed. Don't commit on a tripwire.
- **Browser session state at tripwire**: Playwright window currently on `https://fynla.org/login` MFA screen, email + password submitted, MFA inputs empty.

## Branch / deploy state

- **Branch:** `main` at `1939a89` (PR #265 merged by CSJ).
- **Local vs origin:** in sync.
- **PR #265:** MERGED.
- **csjones.co/fynla:** at `50f58f0` (post PR #264). Out of date relative to main (`1939a89`) but session 14 already verified the runtime fix works there. No re-deploy needed unless future regression suspected.
- **fynla.org production:** **DEPLOYED at `1939a89` runtime equivalent** (11 PHP files + new SPA bundle). Files extracted, optimize cycle clean. **Browser-verification PENDING** — that's what session 16 picks up.
- **F6 retirement deadline:** verified — `grok-4-1-fast-reasoning` not retiring 5/15. No urgency.
