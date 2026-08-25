---
type: handover
mode: context-clear
date: 2026-06-17
session: 2
branch: main
trigger: PreCompact hook fired mid /session-end; this safety-net file was rewritten by the model post-compaction with the real narrative.
worktree: /Users/CSJ/Desktop/fynla
supersedes: handover-2026-06-17-session-1-clear.md (same work; this is the later, wrap-up-complete snapshot)
---

# Context Clear Handover — 2026-06-17, Session 2

> This started life as a minimal PreCompact safety-net (git status + last 3 prompts only).
> It has been rewritten by the model after compaction with the full session narrative,
> matching context-handover quality. It is the authoritative pickup doc for 2026-06-17.
> The earlier `handover-2026-06-17-session-1-clear.md` covers the same work; this one is
> the later snapshot taken at session-end wrap-up.

## Immediate state
The **SaveTax campaign-onboarding verify-after-capture** feature — including the final flow correction CSJ demanded — is **complete and live on PROD (fynla.org)**, browser-verified end-to-end (income section walked on local, csjones AND prod). Working tree clean, on `main`, 0/0 with origin/main. We were in the middle of `/session-end context clear` finishing the wrap-up (memory pointer, CSJTODO, vault mirror, final commit) when the PreCompact hook fired. No code work is outstanding — only the session-end housekeeping.

## The thread (this session's arc — 3 prod releases)
1. **SaveTax verify-after-capture (Option B) + 3 correctness fixes → prod** (PRs #563 → #564). Resume-onboarding-in-dock mixin, `handleCampaignVerifyEdit` (update-only, honesty-gated). Fixes: `Investment\InvestmentAccount` namespace, store-boundary routing (SavingsStore / InvestmentAccountStore / PensionStore), honest verify-edit ack (ack only yielded AFTER the tool fires). Plus the **time-estimate greeting** (3-5 min base + 1 min per asset beyond the first), **de-jank** of the navigate hand-off (smooth close + suppress premature confirm), and the **"Tap the chat below to continue with Fyn" nudge**.
2. **Cookie consent at the landing + Fyn auto-open → prod** (PRs #565 → #566). Cookie banner had only ever been in the web SPA (`App.vue`); the server-rendered landing/funnel had none, so consent first appeared at registration. Added vanilla `public/pages/js/cookie-consent.js` to `index.php` / `savetax.php` / `savetax-plan.php` (same `localStorage['cookie_consent']` key, persists). And the `/m` dashboard now **auto-opens Fyn** with the greeting for onboarding arrivals (`Dashboard.vue mounted()`).
3. **SaveTax verify FLOW correction → prod** (PRs #567 → #568). During the prod E2E CSJ found the flow wrong: a redundant double "anything else?" gate, advice shown BEFORE the confirm, the `/income` screen showing only the amount (not employer/role), and the chat box clearing on navigation. **Reworked to CSJ's exact spec** (see the canonical memory below).

## Files touched (all committed + merged + on prod)
- `app/Services/Onboarding/OnboardingStateMachine.php` — verify flow rewire: `enterCampaignVerify()` → `campaign_verify_navigate` (was `campaign_verify_more`); section capture-ends route into the verify; `campaignSectionAdvice()` section→advice map; `nextFromVerifyNavigate('yes')` → advice → `nextCampaignSection`; all 5 advice states' `next` → `nextCampaignSection`; `nextFromEmploymentMore('No')` → `enterCampaignVerify(income)`. Earlier: `buildFunnelRecapPrompt` time estimate.
- `app/Services/Onboarding/OnboardingChatDirector.php` — `handleCampaignVerifyEdit` (update-only) + store-routed reads + honest ack gate.
- `app/Services/UserProfile/UserProfileService.php` — `incomeSources()` now carries `employer` + `occupation`.
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — verify-state corpus + `{ branch: enterCampaignVerify }` stubs for the 5 re-routed capture-ends (golden-master parity).
- `resources/mobile/mixins/onboardingChat.js` — shared onboarding chat client: **`loadTranscript`** (full chat persistence via `GET /conversations/{id}`); nudge nav whitelist; de-jank (300ms delayed push + suppress premature confirm).
- `resources/mobile/views/Income.vue` — employer · role · amount on the Employment row.
- `resources/mobile/views/Dashboard.vue` + `resources/mobile/components/MobileChrome.vue` — auto-open Fyn, nudge markup, mixin.
- `public/pages/js/cookie-consent.js` (new) + `index.php` / `savetax.php` / `savetax-plan.php`.
- Tests: `CampaignVerifyFlowTest.php`, `CampaignSectionFlowTest.php` updated to the new flow. Full suite 5121 passed / 0 failed.

## What the next Claude needs to know (NON-OBVIOUS — do not re-break)
- **The agreed SaveTax verify sequence (per CSJ, do NOT change):** add the account → ask the ONE existing capture gate ("any other roles/sources?" — existing wording, no new gate) → **No → navigate to the section's screen to confirm (with the nudge)** → user reopens chat (**full transcript shown**) → **"Yes" → THEN the section advice → next section.** Verify comes BEFORE advice; advice comes AFTER the confirm. Canonical memory: `feedback_savetax_verify_sequence_canonical`.
- **`campaign_verify_more` is now an orphaned (unreachable) state** — kept in code + corpus only for golden-master parity. Safe to delete in a future cleanup (touches `inCodeStates` + corpus + the now-passing flow tests).
- **CSJ was very frustrated this session about over-complication.** Use EXISTING patterns / the standard add sequence; don't invent flows or ask questions resolvable from the code. When CSJ says "why are you complicating", STOP and reuse what exists.
- **Verified live on prod: INCOME section only.** The flow change is generic (all sections share the same verify states), the savings/investments/retirement screens already list their records, and chat persistence + auto-open are global — but only income was individually walked on prod.
- All csjones + prod test users cleaned up. No errors in the prod log today.

## Deploy status
**Everything is on PROD (fynla.org) and verified.** `main` = `origin/main` = what's live. csjones (dev) is also current (`origin/dev`). No pending deploy. SSH keys for csjones (`~/.ssh/fynlaDev`) and prod (`~/.ssh/production`) were loaded into the agent by CSJ this session — they will need reloading next session.

## Tech debt found / deferred
- Orphaned `campaign_verify_more` state (above) — remove in a cleanup pass.
- iOS Safari iframe-localStorage partitioning for the cookie banner on `/m` is **untested on a real device** (desktop same-origin iframe shares localStorage; verified there). If a real iPhone re-prompts at registration, pass the consent up from the iframe to the parent.
- `Income.vue` / `Expenditure.vue` local `formatCurrency` (standing mobile-bundle convention).

## Known issues / blockers
- None broken. Feature live + verified.

## Pick up from here
- The SaveTax campaign onboarding is **done and live on prod.** Nothing outstanding from this session's engineering work.
- If continuing: optionally walk the **remaining sections on prod** (savings → investments → pensions → giving → spouse → expenditure) to confirm parity — they reuse the same verify mechanics and their screens already list records, so this is confirmation, not new work. Set up a campaign user via `~/.ssh/production` SSH tinker (`onboarding_fyn_path='campaign'`, `onboarding_fyn_step=null`, consent granted, funnel_answers populated), inject the Sanctum token into `localStorage['m_scaffold_token']`, walk `https://fynla.org/m/app/dashboard`. Clean up the test user after.
- The session-end housekeeping that was interrupted by compaction is being completed now: vault-sync, MEMORY.md pointer for `feedback_savetax_verify_sequence_canonical`, CSJTODO refresh, vault mirror of both handovers, final commit.
