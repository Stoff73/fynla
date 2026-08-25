---
type: handover
mode: context-clear
date: 2026-06-17
session: 1
branch: main
---

# Context Clear Handover — 2026-06-17, Session 1

## Immediate state
Just finished and **deployed to PROD (fynla.org)** the SaveTax campaign-onboarding verify-flow correction, and **walked the income section live on prod end-to-end to confirm it.** The feature is complete and live. Working tree clean, on `main`, 0/0 with origin/main.

## The thread (this session's arc — 3 prod releases)
1. **Landed SaveTax verify-after-capture (Option B) + 3 correctness fixes → prod** (PRs #563 → #564). Resume-onboarding-in-dock mixin, `handleCampaignVerifyEdit` (update-only, honesty-gated). Fixes: `InvestmentAccount` namespace, store-boundary routing (SavingsStore/InvestmentAccountStore/PensionStore), honest verify-edit ack. Plus the **time-estimate greeting** (3-5 min + 1/asset beyond first), **de-jank** of the navigate hand-off (smooth close + suppress premature confirm), and the **"Tap the chat below to continue with Fyn" nudge**.
2. **Cookie consent at the landing + Fyn auto-open → prod** (PRs #565 → #566). Cookie banner was only in the web SPA (`App.vue`); server-rendered landing/funnel had none, so it first appeared at registration. Added vanilla `public/pages/js/cookie-consent.js` to `index.php`/`savetax.php`/`savetax-plan.php` (same `localStorage['cookie_consent']` key, persists). And the `/m` dashboard now **auto-opens Fyn** with the greeting for onboarding arrivals (`Dashboard.vue mounted()`).
3. **SaveTax verify FLOW correction → prod** (PRs #567 → #568). CSJ found during the prod E2E that the flow was wrong. **Reworked to CSJ's exact spec** (see memory).

## Files touched (all committed + merged + on prod)
- `app/Services/Onboarding/OnboardingStateMachine.php` — verify flow rewire (enterCampaignVerify→navigate; section-ends→verify; advice→next; `campaignSectionAdvice` map).
- `app/Services/Onboarding/OnboardingChatDirector.php` — verify-edit handler + store-routed reads + honest ack (earlier in session).
- `app/Services/UserProfile/UserProfileService.php` — `income_summary` now carries `employer`+`occupation`; `income_summary` block (earlier).
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — verify-state corpus + branch stubs for re-routed capture-ends.
- `resources/mobile/mixins/onboardingChat.js` — shared onboarding chat client; **`loadTranscript`** (full chat persistence); nudge nav whitelist; de-jank.
- `resources/mobile/views/Income.vue` — employer · role · amount on the Employment row.
- `resources/mobile/views/{Dashboard,components/MobileChrome}.vue` — auto-open, nudge, mixin.
- `public/pages/js/cookie-consent.js` (new) + `index.php`/`savetax.php`/`savetax-plan.php`.
- Tests: `CampaignVerifyFlowTest.php`, `CampaignSectionFlowTest.php` updated to the new flow.

## What the next Claude needs to know (NON-OBVIOUS — do not re-break)
- **The agreed SaveTax verify sequence (per CSJ, do NOT change):** add the account → ask the ONE existing capture gate ("any other roles/sources?" — existing wording, no new gate) → **No → navigate to the section's screen to confirm (with the nudge)** → user reopens chat (**full transcript shown**) → **"Yes" → THEN the section advice → next section.** Verify comes BEFORE advice; advice comes AFTER the confirm. See memory `feedback_savetax_verify_sequence_canonical`.
- **`campaign_verify_more` is now an orphaned (unreachable) state** — left in code + corpus for golden-master parity. Safe to remove in a future cleanup (touches inCodeStates + corpus + the now-passing flow tests).
- **CSJ was very frustrated this session about over-complication.** Use EXISTING patterns / the standard add sequence; don't invent flows or ask questions you can resolve from the code. When CSJ says "why are you complicating", stop and reuse what exists.
- **Verified live on prod: INCOME only.** The flow change is generic (all sections use the same verify states) and the savings/investments/retirement screens already list their records, and chat persistence + auto-open are global — but only the income section was individually walked on prod.
- All csjones + prod test users cleaned up. No errors in the prod log today.

## Deploy status
**Everything is on PROD (fynla.org) and verified.** main = origin/main = what's live. csjones (dev) is also current (origin/dev). No pending deploy. SSH keys for csjones (`~/.ssh/fynlaDev`) and prod (`~/.ssh/production`) were loaded into the agent by CSJ this session — they will need reloading next session.

## Tech debt found / deferred
- Orphaned `campaign_verify_more` state (above) — remove in a cleanup pass.
- iOS Safari iframe-localStorage partitioning for the cookie banner on `/m` is **untested on a real device** (desktop same-origin iframe shares localStorage; verified there). If a real iPhone re-prompts at registration, pass the consent up from the iframe to the parent.
- `Income.vue`/`Expenditure.vue` local `formatCurrency` (standing mobile-bundle convention).

## Known issues / blockers
- None broken. Feature live + verified.

## Pick up from here
- The SaveTax campaign onboarding is **done and live on prod**. If continuing: optionally walk the **remaining sections on prod** (savings → investments → pensions → giving → spouse → expenditure) to confirm parity — they reuse the same verify mechanics and their screens already list records, so this is confirmation, not new work. Set up a campaign user via `~/.ssh/production` SSH tinker (`onboarding_fyn_path='campaign'`, `onboarding_fyn_step=null`, consent, funnel_answers), inject the Sanctum token into `localStorage['m_scaffold_token']`, walk `https://fynla.org/m/app/dashboard`. Clean up the test user after.
- Otherwise, nothing outstanding from this session.
