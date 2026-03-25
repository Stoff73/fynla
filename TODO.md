# TODO — Fynla

*Last updated: 25 March 2026 — session 8 (AI form fill, edit system, navigation)*

## Completed This Session

### Bug Fixes
- [x] WARN-002: Sessions API 500 — orphaned sessions filtered, try-catch added
- [x] WARN-003: Holistic plan "Cannot read properties of undefined" — v-if guards
- [x] SAYE validation: units_granted/grant_date made optional for SAYE
- [x] Private Co/Crowdfunding card display: £0 → current_value fallback
- [x] Investment edit persistence: accountData parameter mismatch fixed

### AI Form Fill — New Modules (104/104 PASS)
- [x] Investment accounts: 14 types tested with Grok AI
- [x] Trusts: 9 types tested
- [x] Family members: 6 types tested
- [x] Estate gifts: 5 types tested
- [x] Edit/update duplicate detection: verified PASS

### AI Features
- [x] Family name resolution (resolveFamilyNames) — 16/16 unit tests
- [x] Settlor auto-population, CLT auto-recording
- [x] Existing records surfaced in system prompt
- [x] list_records tool, protection edit flow, navigation audit

## Next Session — Priority Tasks

### 1. Investment Detail View Consolidation (USER PRIORITY)
User wants card-driven per-account view. MUST discuss exact UX before coding.

### 2. Edit Testing (remaining 11 scenarios)
Test update_record across all entity types.

### 3. Merge & Deploy
Merge grokAI to main, deploy to production.

## Tech Debt
- [ ] OnboardingWizard.vue: Vue warn
- [ ] LiabilitiesStep.vue: DEPRECATED
- [ ] IncomeStatementTab.vue: orphaned
- [ ] DB enum missing step_child/partner — handler maps as workaround

## Context for Next Session

Key files: March/March25Updates/aiUpdates.md, March/March24Updates/deployAI.md (104 tests), March/March25Updates/editUpdatePlan.md
