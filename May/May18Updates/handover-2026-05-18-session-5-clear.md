---
type: handover
mode: context-clear
date: 2026-05-18
session: 5
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~354k / 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 5

## Immediate state

Tripled capture-ack is **RESOLVED and browser-GREEN** (single + multi-entity
under live unified) AND the **post-fix clean parity gate is GREEN** (legacy &
unified both `1 skipped, 3728 passed` — identical, baseline-matching, zero
regressions; `/tmp/fyn-parity-postfix.log`). Both handover step-2 (classify)
and step-3 (parity-clean + tripled-ack resolved) gates are satisfied. The
`fynPromptRework → dev` PR is now **unblocked**. WIP commit `db47635` pushed.

## The thread

- Auto-resumed session-4-clear. Step 1 (parity-clean gate `b12uga51v`): read
  it GREEN (legacy/unified both 3728/1) — step 1 done.
- Step 2 classification (browser, john user_id=11, `onboarding_completed=false`
  → AdviceFyn): **unified** captures the row but streamed+persisted
  `"Got it — recording that now."` ×3; **legacy** does NOT triple — it
  security-refuses the whole advice→capture write journey (0 rows; reproduced
  2× Halifax + Lloyds). ∴ tripled-ack is **unified-specific**; "mirror legacy"
  (handover's prescribed remedy) is impossible (legacy never creates here).
- Branch diff `origin/dev...HEAD` = 4 unified-path files only; the
  OnboardingChatDirector change is `FynPromptMode::isUnified()`-gated &
  documented "no-op under legacy" → **legacy-refusal is pre-existing on
  origin/dev**, NOT this branch's blocker (per handover contract) but it
  breaks emergency-rollback safety (Pest can't catch — LLM mocked).
- Surfaced both findings + 3 fix options to CSJ via AskUserQuestion. CSJ chose
  **Fix A (tool-result terminal signal)** + **log-legacy-separately, proceed
  per contract**.
- Two coupled root causes found while looping (Rule #15 — fixed in-loop):
  (1) model emits invalid `account_type:"savings_account"` → hard-rejected by
  `Rule::in` → `validation_failed` → no row on first call (only sometimes
  self-corrected on retry → intermittent zero rows); (2) no turn-complete
  signal in the data_capture `HasAiChat` loop.
- First Fix-A attempt fired the directive on the tool NAME (`create_`) even
  on a failed create → suppressed the retry → no row, doubled ack. Corrected
  to fire **only on a landed/deduped result shape**.
- Final fixes: `CoordinatingAgent` +37 (coerce unknown/synonym account_type
  to `easy_access`/`cash_isa` BEFORE validation); `HasAiChat` +75
  (`captureTurnCompleteDirective()`, persona==='data_capture'-gated, only on
  landed/deduped). Pint passes. Browser-GREEN both cases. Parity GREEN.

## Files touched this session

```
TRACKED (WIP db47635, pushed):
  app/Agents/CoordinatingAgent.php  | +37  account_type coercion pre-validation
  app/Traits/HasAiChat.php          | +75  captureTurnCompleteDirective + hook
  May/May18Updates/fyn-tripled-ack-classification.md | investigation+resolution
```
(`fynPromptRework` already carried 4 unified-path files vs origin/dev from
session 4: FynContextAssembler +11, FynTurnContext +3, OnboardingChatDirector
+92/-33, HasAiChat +3 — those are PR #332-era / session-3 WIP `9c19dcc`.)

## WIP commit

- SHA: `db47635` — `wip: context-handover snapshot`
- Pushed: **yes** (`8d5692c..db47635 fynPromptRework`)
- Squash targets before the PR: `9c19dcc` (session-3 WIP) **and** `db47635`
  (this session's fix) → one clean feature commit.

## Open decisions

- **Legacy rollback-safety** (separate, pre-existing, NOT this PR's blocker):
  legacy `FYN_PROMPT_ARCH=legacy` security-refuses the advice→capture write
  journey entirely. Default direction of travel (CSJ chose "log separately,
  proceed per contract"): proceed to the dev PR now; track legacy/rollback as
  a separate logged item. NOT yet written into CSJTODO or a memory file —
  next session should formalise it (task #4).
- No other undecided items — CSJ answered fix approach (A) and PR gate
  (proceed per contract) via AskUserQuestion this session.

## Pick up from here (auto-continue contract)

1. **Both gates are GREEN — proceed to the dev PR.** Squash `9c19dcc` +
   `db47635` into one clean feature commit on `fynPromptRework`
   (interactive rebase is blocked in this env — use a non-interactive
   reset+recommit or `git rebase --onto`/`git reset --soft origin/dev` then
   one commit). Do NOT touch the 4 session-4 unified-path files'
   *behaviour* — only collapse history. Open `fynPromptRework → dev` PR.
   **No self-approve** (memory `feedback_no_self_approval`); CSJ is sole
   reviewer — use the established admin-merge pattern only when CSJ says so
   (memory `feedback_admin_merge_pattern_for_solo_reviewer_prs`), do not
   auto-merge.
2. **Formalise the legacy-refusal finding** (task #4): add a memory file
   (`reference_legacy_refuses_advice_capture_journey.md` or similar) +
   a CSJTODO line — pre-existing on origin/dev, breaks emergency-rollback
   for advice→capture writes, Pest-invisible (LLM mocked). Cross-link from
   `May/May18Updates/fyn-tripled-ack-classification.md`.
3. Carried/unchanged from session-4: vault-sync still overdue (CSJTODO #1
   CRITICAL — `April/April24Updates/spec/00-canonical.md` gitignored, data-
   loss risk); legacy rollback sanity; tech-debt-session on the diff.

## What the next Claude needs to know

- **Do NOT re-run the parity gate to "re-confirm"** — it is GREEN post-fix
  (`/tmp/fyn-parity-postfix.log`, may be gone after reboot; result recorded
  here + in the classification doc: legacy 3728/1, unified 3728/1). Only
  re-run if you change code again.
- The fix's `HasAiChat` directive is `persona==='data_capture'`-gated →
  inert on advice + the legacy-refusal path. The account_type coercion is
  flag-agnostic, pure-additive (only rewrites an already-invalid value).
- Post-fix behaviour is "ack + distinct confirmation" (e.g. "Got it —
  recording that now." then "Saved your Lloyds savings account."), NOT a
  single ack — this is correct/desired (acknowledge → confirm), the
  verbatim ×3 repetition is what was eliminated. Don't "fix" it back to one.
- Dev server: stopped (killed for the clean parity gate). Vite was on 5173
  (pid since killed). `public/hot` went MISSING mid-session once → caused a
  transient `/fynla/` base redirect (stale csjones build served); fixed by
  clean `npm run dev` restart (memory `feedback_public_hot_stale_chunks`).
  session-start Phase 1e will restart dev cleanly (unified default — do NOT
  pass FYN_PROMPT_ARCH; it's unset in .env, config default is unified).
- DB (john user_id=11) test rows created this session: Starling 308, Lloyds
  309, Tesco Bank 310, Santander 311 (+ Monzo 284 from session 4). Halifax/
  Lloyds legacy attempts created nothing. These are test artefacts — leave
  them; `db:seed` won't remove them (john is a TestUsersSeeder user, not
  preview). Not a concern for the PR.
- Worktree `/.claude/worktrees/tender-bassi-375ee8` on branch `freemium`
  (HEAD 5a5478b, clean) — separate sub-project 2, leave intact.
- Memory: `feedback_loop_until_correct`, `critical_browser_testing_law`,
  `feedback_advice_fyn_is_read_only`, `feedback_eval_must_drive_full_user_journey`
  all directly relevant to this work.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (WIP `db47635` pushed)
- Deploy status: Not deployed (feature branch; dev PR not yet opened —
  next session's first action per "Pick up from here")
