---
type: handover
mode: context-clear
date: 2026-05-17
session: 3
branch: fynPromptRework
trigger: context-handover skill (context tripwire @ ~272k)
---

# Context Clear Handover — 2026-05-17, Session 3

## Immediate state

**Journey (b) write-intent fix is APPLIED + unit-green, but the decisive Rule #15 browser re-verification under unified was NOT yet run.** Just re-logged john@example.com into `:8000` (unified), landed on `/dashboard`; tripwire fired on the baseline-savings-count tinker call before the browser re-test could execute. WIP commit `9c19dcc` pushed; tree clean.

## The thread

- CSJ was furious that the prompt rework sat behind `legacy` default and that I kept framing advice/onboarding as live "prompts". Corrected: there is ONE prompt (`FynSystemPrompt` + `FynContextAssembler`); legacy = rollback-only.
- **CSJ explicitly decided the cutover** ("put the new prompt in, like I asked designed and planned"). Done: `config/fyn.php` default flipped `legacy` → `unified` (no `.env` override; CLI + HTTP both resolve `unified`).
- PR #332 was **merged to dev** earlier this session by CSJ direction (`gh pr merge 332 --merge --admin`, merge commit `8d1ba2b`). NOTE: this was before the cutover/bug-fix commits below — those are NOT in dev yet.
- Independently re-verified full suite (pre-cutover, default still legacy at that point): legacy 3726/1, unified 3726/1 — exact parity.
- Browser journeys under unified: (a) advice read-only **GREEN** (accurate "no pension records", hedged, FCA signpost). (b) write-intent **RED** — "Add a savings account with Barclays £3,000…" → Fyn returned security-clause-6 refusal, **no DB write**.
- systematic-debugging → **root cause (code-confirmed):** `OnboardingChatDirector::handleInlineCapture` (the advice-mode write handoff target for BOTH the deterministic `AdviceFyn:333` path AND the LLM `delegate_to_capture`→`wrapStream` path) was never wired into the unified seam. It never calls `setUnifiedOnboardingFocus`, so `HasAiChat::injectUnifiedTurnContext` (`:854`) builds an **advice-mode** context, `FynContextSelector` never returns CAPTURE, `FynCaptureTurnInstructions` is never injected → model has no extract-and-create framing → falls back to security-clause-6. **Structural plan gap (Task 7/8 missed handleInlineCapture), not a #332 regression.**
- Fix applied (mirrors the working `handleAssetCaptureTurn` pattern): in `handleInlineCapture`, under `FynPromptMode::isUnified()`, derive focus via existing `inferFocusesFromEntityTypes($context->entityTypes)[0]`, `setUnifiedOnboardingFocus($focus)` before `chatWithPromptOverride`, reset to `null` in a `finally`.
- Flipping config default to unified made the whole suite run unified-by-default → 3 strict `CoordinatingAgent` mocks now receive the new legitimate `setUnifiedOnboardingFocus()` call. Fixed with the **documented sanctioned non-weakening idiom** `->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();` (same as parity-record's 6 sites). Targeted suite **26 passed**.

## Files touched this session (WIP `9c19dcc`)

```
config/fyn.php                                     (cutover: default legacy->unified)
app/Services/Onboarding/OnboardingChatDirector.php (handleInlineCapture unified-seam wiring + try/finally)
tests/Feature/Fyn/InlineCaptureFlowTest.php        (mock idiom)
tests/Feature/Fyn/AdviceFynRoutesWritesViaHandoffTest.php (mock idiom)
tests/Feature/Fyn/AdviceFynHandoffErrorTest.php    (mock idiom, 2 sites)
```

## WIP commit

- SHA: `9c19dcc` (on top of `1419fc2`)
- Pushed: **yes** (`origin/fynPromptRework`)
- Squash into a proper `fix(fyn): wire handleInlineCapture into unified seam + cutover to unified default` before any dev→ PR. PR #332 already merged; these commits need a NEW PR fynPromptRework → dev.

## Open decisions

- None blocking. CSJ already decided the cutover (unified = default). Default direction of travel: **continue Rule #15 loop on journey (b) until browser-GREEN under unified**, then re-walk (a) and (c), then full suite under unified default.

## Pick up from here (auto-continue contract)

1. **Resume the Rule #15 loop — browser re-verify journey (b) under unified (NOT yet done).** Server `:8000` already carries unified (config default now unified anyway). Login `john@example.com`/`password` (local MFA: fetch from DB per CLAUDE.md). Baseline: `SavingsAccount::where('user_id',11)->count()` (was 1 — only polluted #165 Nationwide). Open Fyn chat, send: `Add a savings account with Barclays, £3,000, easy access, 4.2% interest`. **Expect (acceptance):** a NEW SavingsAccount row for uid 11 (Barclays, 3000, non-ISA), ≤15-word ack, NO security-clause-6 refusal. Cross-check the DB row before claiming green. If still RED → systematic-debugging again with the new evidence (do NOT hand back; loop per Rule #15).
2. Then re-walk journey (a) (advice read-only) + journey (c) (multi-entity onboarding, fresh/clean user) under unified to confirm no regression from the handleInlineCapture change.
3. Then full suite under the NEW unified default: `./vendor/bin/pest` (now = unified). Fix any further strict-`CoordinatingAgent`-mock sites with the SAME sanctioned idiom (`->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();`). Also sanity re-run `FYN_PROMPT_ARCH=legacy ./vendor/bin/pest` to confirm legacy rollback path still green.
4. Squash `9c19dcc` into a proper commit; open NEW PR `fynPromptRework → dev` (PR #332 already merged — do NOT reopen it). Do NOT self-approve.

## What the next Claude needs to know

- **One prompt only.** Do not reintroduce advice/onboarding-prompt framing. Legacy = rollback. CSJ corrected this twice — do not relitigate.
- **Cutover is intentional and CSJ-ordered.** `config/fyn.php` default = `unified`. Do NOT revert it to legacy "to be safe". The whole point is unified IS the prompt now.
- The mock idiom is **sanctioned + non-weakening** (zero-call-satisfied under legacy, other expectations stay strict) — precedent in `tests/Feature/Onboarding/AssetCaptureGapFillTest.php` etc. Not test-weakening; do not flag it.
- Test pollution carried forward (harmless, `updateOrCreate`-safe, do NOT `migrate:fresh`): john (uid 11) has `SavingsAccount` #165 Nationwide Cash ISA £5k from a prior session's journey (b). Use a distinguishable record (Barclays £3,000) so before/after is unambiguous.
- `:8000` artisan serve re-includes PHP per request and config is uncached (`bootstrap/cache/config.php` absent) — code/config changes are live without restart.
- Vite :5173 canonical — do NOT `pkill -f vite`.
- 6-box MFA on `/login` needs ONE digit per box (fill box-by-box; bulk fill only lands the first digit).
- **STILL OVERDUE (unchanged from session 1/2):** EOD `/session-end` must run vault-sync + carry the rewritten `April/April24Updates/spec/00-canonical.md` into the vault (`/April/` gitignored — lost on next `/April/`-tree change). Plus tech-debt-session on this session's diff.

## Branch / deploy state

- Branch: `fynPromptRework`
- Ahead of origin: 0 (WIP `9c19dcc` pushed)
- Behind origin: 0
- Relative to `dev`: PR #332 (`8d1ba2b`) merged the PRE-cutover state. The cutover + handleInlineCapture fix (`9c19dcc`) are NOT in dev — need a new PR after squash + browser-green.
- Deploy status: **Not deployed.** No csjones/prod deploy this session.
