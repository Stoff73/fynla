# TODO — Fynla

*Last updated: 24 March 2026 — session 5 (AI form fill testing, onboarding fix, UI fixes)*

## Completed This Session

### AI Form Fill Testing (grokAI branch)
- [x] Protection module: tested all 8 policy types with Anthropic (4/8 pass) and Grok (7/8 pass)
- [x] Investment module: added 9 new account types to AI tool definition (VCT, EIS, private company, crowdfunding, SAYE, CSOP, EMI, unapproved options, RSU)
- [x] Added 25+ type-specific fields (bonds, private company, employee share schemes)
- [x] Fixed VCT/EIS not showing private company fields (isPrivateInvestmentType)
- [x] Fixed 422 validation: company_registration_number cast to float instead of string
- [x] Fixed Anthropic tool data leak in responses (system prompt instruction)
- [x] Centralised form fill handshake in aiFormFill.js (formReady state, 30s fallback, stale state clearing)
- [x] Added validation error reporting from form auto-submit back to chat
- [x] Documented all results in March/March24Updates/AI/

### Onboarding Bug Fix (main branch)
- [x] Fixed stale lifeStage causing onboarding welcome screen to be skipped on re-registration
- [x] Root cause: auth.js fetchUser only set lifeStage when truthy, leaving stale data from previous user
- [x] Fix: always sync lifeStage from API (including null) on every login
- [x] Added resetState mutation to lifeStage store
- [x] Tested on production: new registration flow works correctly

### UI Fixes (fixBugs branch — PR #159 merged)
- [x] Replaced investment info banner with hover tooltip icon
- [x] Reduced net worth donut charts by 25%
- [x] CSP header fix for Vite dev server
- [x] API base URL fix for local development

### Production Testing
- [x] Tested new user registration on fynla.org (chris@fynla.org)
- [x] Full onboarding flow: welcome → Building Foundations → 5 steps → dashboard
- [x] Tested c.jones@csjones.co login
- [x] Reproduced onboarding skip bug after account delete + re-register → fixed

## Deployed to Production
- [x] Onboarding lifeStage fix (auth.js, lifeStage.js)
- [x] Investment tooltip + donut chart resize (PR #159)
- [ ] Upload public/build/ to server (built, ready to upload)
- [ ] Upload SecurityHeaders.php
- [ ] Clear caches on server

## Not Yet Deployed (grokAI branch)
- [ ] AI form fill: 14 investment types + specialised fields
- [ ] Protection form fill fixes
- [ ] AI form fill handshake + degradation fix
- [ ] System prompt: no tool data leak instruction

## Known Issues

### AI Form Fill — Conversation Length Degradation
- After ~7 consecutive form fills in one chat session, subsequent fills fail silently
- Root cause: NOT the fallback timer (investigated). The form fill pipeline works (debug logs confirm save emitted). The failure is downstream — the API call after form submit fails silently.
- Likely cause: API throttling or request interference from SSE stream
- Workaround: batch ~5 fills per conversation
- Fix plan: March/March24Updates/AI/conversation-degradation-fix-plan.md

### AI Form Fill — Remaining Entity Types Untested
- [ ] DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow

### Protection Form Fill Issues
- [ ] Family Income Benefit: £0 sum assured (benefit_amount mapping)
- [ ] `family_income_benefit` and `term` not in life_policy_type dropdown
- [ ] Silent form submission failures for some types with Anthropic

## Tech Debt
- [ ] Debug console.log statements in AccountForm.vue (remove before deploy)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue is orphaned (never imported)
- [ ] WARN-002: Security sessions API returns 500
- [ ] WARN-003: Vue error on holistic-plan page

## Grok AI Migration (branch: grokAI)

**Status:** All implementation phases complete. AI form fill testing in progress.
**Plan:** March/March23Updates/grokMigrationPlan-v2.md

### Remaining
- [ ] xAI API key configured and tested locally
- [ ] Phase 5 remaining: remove Anthropic SDK, delete Python scripts, update legal text
- [ ] Merge grokAI branch to main
- [ ] Deploy to production, switch via admin panel

## Context for Next Session

Session 24 March: Extensive AI form fill testing across Protection (8 types) and Investment (14 types) modules with both Anthropic and Grok. Grok consistently outperforms Anthropic on form fill reliability. Identified conversation-length degradation issue (fills fail after ~7 in one session). Fixed onboarding bug on production (stale lifeStage on re-registration). UI fixes merged (PR #159). Production build ready to upload.

Key files:
- AI test results: March/March24Updates/AI/protection.md, investments.md
- Degradation fix plan: March/March24Updates/AI/conversation-degradation-fix-plan.md
- Deploy guide: March/March24Updates/deployBugs.md
