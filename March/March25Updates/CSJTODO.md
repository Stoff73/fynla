# TODO — Fynla

*Last updated: 24 March 2026 — session 6 (AI form fill: protection, business, goals, life events, investment holdings)*

## Completed This Session (grokAI branch)

### AI Form Fill — New Modules (all Grok-tested in browser)
- [x] Protection: 8/8 types (level term, decreasing, whole of life, FIB, standalone CI, accelerated CI, income protection, generic term)
- [x] Business Interests: 4/4 types (sole trader, limited company, partnership, LLP)
- [x] Goals: 9/9 types (emergency fund, home deposit, holiday, wedding, car, education, debt repayment, wealth building, custom)
- [x] Life Events: 16/16 types (9 income + 7 expense, all certainty levels)
- [x] Investment Holdings: create_holding tool built, manual test passing, Grok test passing (with caveats)

### Fixes Applied
- [x] Protection: added family_income_benefit to dropdown, term→level_term mapping, FIB benefit_amount→coverage_amount
- [x] Business: enriched tool with industry_sector, revenue, dividends, employee_count. Pre-set business_name/type/valuation
- [x] Goals: enum updated to match backend (home_deposit, car_purchase, etc), custom_goal_type_name auto-set, cancelFill→completeFill
- [x] Life Events: tool rewritten with full 16-type enum, event_name param, certainty param, cancelFill→completeFill
- [x] All forms: filling watchers upgraded to 500ms/$nextTick/error reporting to chat
- [x] aiProcess.md written — development process documentation

## CRITICAL — Investment Module Needs Rework

### Investment Accounts — NOT WORKING
- [ ] `create_investment_account` tool exists but Grok creates accounts with £0 value
- [ ] Never properly tested with Grok following the full process (manual fill → verify → algorithm → test)
- [ ] Needs full process run for at minimum: ISA, GIA, bonds (the common types)
- [ ] The existing tool has ~40 parameters — most are for niche types (employee share schemes, private company)

### Investment Holdings — Partially Working
- [ ] `create_holding` tool and handler built, manual form fill works
- [ ] Grok test: form fills and saves correctly BUT matched wrong account (ISA instead of GIA)
- [ ] Account lookup LIKE query too loose — picks most recent match when multiple accounts share provider name
- [ ] Fund asset_type requires sub_type (backend validation) — ETF is safer for AI

### Investment Process
- [ ] Follow aiProcess.md strictly: manual fill each account type → verify DB → write algorithm → test with Grok
- [ ] Start with ISA and GIA (most common), then bonds, then VCT/EIS if time

## Known Issues

### Console Errors (non-blocking)
- [ ] Protection TypeError at PolicyFormModal.vue:196 during AI fill — doesn't block save
- [ ] property_sale life event: Grok also creates property record (double navigation)

### Tech Debt
- [ ] Debug console.log statements in AccountForm.vue (remove before deploy)
- [ ] Duplicate Vanguard ISA accounts created during testing (clean up)

## Not Yet Started
- [ ] Trusts AI form fill
- [ ] Family Members AI form fill
- [ ] Estate Gifts AI form fill (separate from liabilities)

## Context for Next Session

Session focused on AI form fill across 5 new modules. Protection, Business, Goals, and Life Events are all working with comprehensive type coverage (68 scenarios). Investment module is the big remaining gap — the account creation produces £0 accounts and the holding lookup matches wrong accounts. Next session MUST start with investments following the full aiProcess.md protocol: read form → fill manually → verify DB → write algorithm → code → test with Grok. The process exists for a reason — it caught the fund/sub_type validation issue during manual testing that would have wasted hours in AI testing.

Key file: `March/March24Updates/AI/aiProcess.md` — the development process. Follow it.

## Files to Review

- `app/Agents/CoordinatingAgent.php` — handleCreateInvestmentAccount handler (exists from previous session, untested with Grok)
- `app/Services/AI/XaiToolDefinitions.php` — create_investment_account tool definition (40+ params, may need simplifying)
- `resources/js/components/Investment/AccountForm.vue` — 1171 lines, 3 sub-components, 14 account types
