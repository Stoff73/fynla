---
type: handover
mode: end-of-day
date: 2026-06-04
session: 1
branch: dev
previous_session: 2026-06-03 session-2 (context-clear)
---

# Handover — 2026-06-04, Session 1

## Where we left off

Spent the whole of 2026-06-03 building the **mobile acquisition + onboarding arc** end-to-end: phone → responsive `/m` landing funnel → `/savetax` 5-question funnel → register (account created from the funnel button) → mobile dashboard → **Fyn greets, recaps the funnel answers, and runs the onboarding (with bubbles) toward tax-optimisation advice**. All shipped to **dev / csjones** across PRs #452–#460 and verified live in the browser. **Nothing went to production.** The feature is functionally complete; the main open item is walking the mobile onboarding all the way to the `/tax-strategy` terminal and confirming the tax advice renders well in the mobile context.

## What shipped today (PRs #452–#460, all merged to dev + deployed to csjones)

- **#452** `fix/mobile-phone-entry-responsive` — **a wrong turn**: disabled the phone→`/m` redirect so phones got the responsive homepage. CSJ corrected: phones must stay routed through the `/m` mobile view. **Reverted in #454.**
- **#453** folded **CLAUDE.md Rules #17–19** (build-to-spec / lean cadence / internalise plans) into the canonical CLAUDE.md.
- **#454** revert of #452 — restored phone→`/m`.
- **#455** `/m` mobile view now **iframes the real responsive homepage funnel** (not the placeholder). `RedirectPhoneToMobile` skips `Sec-Fetch-Dest: iframe` loads (no loop); `SecurityHeaders` grants SAMEORIGIN + `Vary: Sec-Fetch-Dest` for framed loads; iOS `svh/dvh` host hardening.
- **#456** **authed handoff** — a router guard swaps the `/m` iframe to `/m/app` once authenticated, **bridging the Sanctum token** (`sessionStorage:auth_token` → `localStorage:m_scaffold_token`). Also fixed mobile `api.js` to be **Bearer-only** (`credentials: 'omit'`) — a real bug where a stale web-session cookie authenticated the wrong user.
- **#457** homepage hero CTA "Get started for free" → `/savetax` (enters the funnel).
- **#458** **funnel answers persist** through registration (`funnel_answers` JSON on `pending_registrations` + `users`; mirrors signup_source). The compact "Register for free" button on `/savetax/plan` now **creates a real account** (CSRF-primed `/auth/register`), then hands to the existing `/register` verify screen.
- **#459** **Fyn consumes the funnel answers**: `FunnelAnswersMapper` pre-fills employment + marital at registration; `buildFunnelRecapPrompt` makes Fyn greet + recap on the first onboarding turn; pre-fill makes `base_employment` auto-skip + `base_personal` acknowledge marital; the savetax campaign onboarding is triggered off durable `funnel_answers` (survives the mobile handoff dropping `?from=savetax`).
- **#460** **mobile Fyn dock wired to onboarding**: `apiStream` surfaces full SSE events; the dock now runs the real onboarding (greet + recap + free-text + **bubble** turns + **resume** loads the transcript) instead of the static mockup chat. **Dashboard nudge** ("Finish your personalised tax plan with Fyn") points funnel users into Fyn.

## What's in flight (NOT done)

- **Mobile onboarding not walked to the terminal.** Verified live: recap greeting, DOB free-text turn, dependants bubble turn, bubble tap advances, pre-fill skips (employment + spouse), and resume. **Did NOT** drive the full campaign flow (income → expenditure → ISA/bank/investment/pension/charitable/spouse holdings) to `STATE_CAMPAIGN_TERMINAL` → `/tax-strategy` in the mobile dock, nor confirm the `/tax-strategy` tax-optimisation advice renders acceptably inside the mobile surface. **This is the top thing to finish.**
- **Desktop Fyn recap** is wired via the same backend (verified at the API/`onboarding/start` level) but not browser-walked on the desktop SPA specifically.
- **No production deploy** of any of this session's work (or the SP3 funnel work). dev is far ahead of main.

## Deploy status

- **Deployed to dev (csjones.co/fynla) and verified live** — all of #452–#460. csjones is on `dev @ 6d30719`. Both bundles (main SPA + `m-build`) rebuilt + uploaded; migrations run (`funnel_answers`).
- **Nothing to production (fynla.org).** dev is **41 commits ahead of main** (this session + CoALA-era backlog) and 7 behind. A `dev → main` release is a sizeable, CSJ-owned decision — NOT recommending it here. `main` also still carries the context-watch tripwire + lacks Rules #17–19 (fold in on the eventual release).

## Tech debt found this session

- **`Current State/Auth.md` is stale (16 days, last touched May 18)** — this session changed mobile auth/onboarding/registration/phone-UA routing. Vault-sync flagged it; not updated. Worth refreshing with the mobile auth flow + funnel integration.
- **Mobile Fyn dock has no automated tests** (frontend Vue) — verified live in the browser only. Backend has `FunnelAnswersCaptureTest` + 335 auth/onboarding tests green.
- **`/savetax/plan` register is a real flow now, but the funnel-answer personalisation JS still reads `localStorage('savetax_answers')`** (the v4 mockup origin) — fine, but the page is mockup-derived.
- Codebase metric drift: PHP Services 345 (CLAUDE.md says 340), Models 123 (says 119) — minor, from this session's new services. Not updated in CLAUDE.md.

## Known issues / blockers

- **None broken.** The whole funnel→register→mobile-dashboard→Fyn-onboarding chain is verified working live on csjones.
- Open question (not a blocker): does `/tax-strategy` (the campaign terminal) present well inside the `/m` mobile surface? Unverified.

## Rules reinforced this session

- **There is ONE Fyn** — do not frame work as routing between "onboarding Fyn" vs "advice Fyn" to CSJ; it's one unified Fyn (canonical contract). CSJ corrected this sharply.
- **An approved design PR means the design takes over the live routes** — don't leave scaffolding/placeholders live; wire the real design. (This is CLAUDE.md Rule #17, folded in via #453.)
- **Don't invent; follow the PR** — when unsure what a PR delivers, READ it fully before acting.
- New/updated memory: `reference_mobile_phone_entry_responsive.md` (phone→/m kept; iframe hosts real funnel; token-bridge handoff; do NOT re-add the reverted `MOBILE_PHONE_REDIRECT` flag).

## Next session should

1. **Walk the mobile onboarding to the end** in the Fyn dock on csjones (use a fresh funnel registration, or reset a test user): answer through income → expenditure → the `CAMPAIGN_*` holdings states → `STATE_CAMPAIGN_TERMINAL`, and confirm it lands on `/tax-strategy` and the tax-optimisation advice renders acceptably in the mobile surface. Fix any bubble/free-text turn that doesn't render in the dock. Loop until green per Rule #15.
2. Spot-check the **desktop** Fyn funnel recap (register a funnel user on desktop, open Fyn, confirm the recap).
3. Consider refreshing **`Current State/Auth.md`** with the mobile auth + funnel flow.
4. Optional cleanup: remove csjones staging test users **`Funnel Tester` #72** and **`Cleo` #73** (created during testing; Cleo was reset to a fresh post-mapper onboarding state).
5. Leave the `dev → main` release to CSJ's call.

## Context hints

- Active branch type: mixed (dev — feature work + reverts + docs)
- Behind origin/main by: 7 ; ahead by: 41
- Uncommitted: none of mine — working tree clean (untracked `June/` will be committed with this handover; `docs/mobile/designer-brief.pdf` is NOT-MINE, leave it)
- Last commit: `6d30719` Merge pull request #460 (mobile Fyn dock onboarding + nudge)
- csjones: `dev @ 6d30719`, live + verified. Both bundles rebuilt this session.
