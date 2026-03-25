# TODO — Fynla

*Last updated: 25 March 2026 — session 7 (inline investment holdings feature + AI tool updates)*

## Completed This Session (grokAI branch)

### Inline Investment Holdings Feature
- [x] Design spec: brainstormed, reviewed, approved (`docs/superpowers/specs/2026-03-24-integrated-investment-holdings-design.md`)
- [x] Implementation plan: written, reviewed (`March/March24Updates/investment-holdings-plan.md`)
- [x] Backend: `StoreInvestmentAccountRequest` — holdings array validation + total allocation check
- [x] Backend: `InvestmentController::storeAccount()` — DB::transaction for account + holdings + auto-cash
- [x] Backend: 5 Pest tests passing (create with holdings, without, explicit cash, >100% rejection, 100% no auto-cash)
- [x] Frontend: `InlineHoldingsEditor.vue` — new spreadsheet-style component (286 lines)
- [x] Frontend: `AccountForm.vue` — embedded editor, HoldingForm for details, both watchers updated
- [x] Frontend: `InvestmentDetailInline.vue` — always-visible holdings section with Details links
- [x] Browser tested: ISA created with 2 holdings + auto-cash, detail view shows all 3, Details modal opens

### AI Tool Updates (code written, NOT tested with Grok)
- [x] `XaiToolDefinitions.php` — added `holdings` array param to `create_investment_account`
- [x] `CoordinatingAgent.php` — `handleCreateInvestmentAccount` passes holdings through for holdable types
- [x] Updated `create_holding` description to clarify standalone vs inline usage
- [x] Algorithm doc rewritten: `March/March24Updates/AI/investment-holding-form-algorithm.md`
- [x] Process doc updated: `March/March24Updates/AI/aiProcess.md` (inline sub-entity pattern)

## CRITICAL — AI Form Fill NOT TESTED WITH GROK

The AI tool updates (XaiToolDefinitions + CoordinatingAgent) were coded but Steps 4-10 of the aiProcess.md were NOT completed:

- [ ] Step 4: Manual browser fill for EVERY variant (ISA, GIA, bond, VCT, EIS with holdings)
- [ ] Step 5: Verify DB save and dashboard display for each variant
- [ ] Step 6: Algorithm doc needs updating AFTER manual testing confirms it works
- [ ] Step 10: Test with Grok — send natural language prompts, verify form fills, verify DB saves

### Investment Accounts — Still Needs Full Process
- [ ] `create_investment_account` with holdings — UNTESTED with Grok
- [ ] Previous issue: Grok creates accounts with £0 value — may still be broken
- [ ] Account lookup LIKE query too loose — picks wrong account when multiple share provider name

## Known Issues (Carried Forward)

- [ ] AI form fill: remaining entity types untested (DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow)
- [ ] Console errors: Protection TypeError at PolicyFormModal.vue:196 during AI fill (non-blocking)
- [ ] property_sale life event: Grok also creates property record (double navigation)

## Tech Debt
- [ ] Debug console.log statements in AccountForm.vue (remove before deploy)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue is orphaned (never imported)
- [ ] WARN-002: Security sessions API returns 500 on /api/auth/sessions
- [ ] WARN-003: Vue error on holistic-plan page

## Grok AI Migration (branch: grokAI)

### Next Session Tasks
- [ ] Get xAI API key from https://console.x.ai
- [ ] Set AI_PROVIDER=xai and XAI_API_KEY in local .env
- [ ] Complete AI form fill testing — follow aiProcess.md Steps 4-10 for investment holdings
- [ ] Test with xAI locally — chat, streaming, tool calling, navigation
- [ ] Test document extraction with xAI
- [ ] Phase 5 remaining: remove Anthropic SDK, delete Python scripts, update legal text
- [ ] Merge grokAI branch to main
- [ ] Deploy to production

## Context for Next Session

Session 25 March built the inline investment holdings feature (design → plan → implement → browser test). The feature works — accounts can be created with inline holdings in a single transaction, and the detail view shows all holdings. However, the AI tool updates (XaiToolDefinitions + CoordinatingAgent) were coded WITHOUT following the aiProcess.md properly — Steps 4-10 were skipped. Next session MUST start by completing the AI form fill process: manual browser fill of each variant, DB verification, then Grok testing. DO NOT write more code until manual testing is done.

Key process file: `March/March24Updates/AI/aiProcess.md` — follow it step by step, no shortcuts.

## Files to Review
- `app/Services/AI/XaiToolDefinitions.php` — holdings param added but untested
- `app/Agents/CoordinatingAgent.php` — holdings passthrough added but untested
- `March/March24Updates/AI/investment-holding-form-algorithm.md` — needs updating after manual testing
