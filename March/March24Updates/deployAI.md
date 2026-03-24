# Deploy Guide — xAI AI Form Fill (24 March 2026)

**Branch:** `grokAI`

## What Changed

Separate xAI-optimised tool definitions with strict function calling. Grok now fills in forms across 10 modules end-to-end via the Fyn chat assistant: Property, Pensions, Chattels, Cash/Savings, Expenditure, Liabilities, Protection, Business Interests, Goals, and Life Events.

## Files to Upload

### New File (1)

| File | Server Path |
|------|-------------|
| `app/Services/AI/XaiToolDefinitions.php` | `~/www/fynla.org/public_html/app/Services/AI/XaiToolDefinitions.php` |

### Modified Files

| File | Server Path | What Changed |
|------|-------------|-------------|
| `app/Traits/HasAiChat.php` | `~/www/fynla.org/public_html/app/Traits/HasAiChat.php` | Routes xAI to XaiToolDefinitions, removes double-wrapping, strengthened system prompt for immediate tool calling, expenditure tool routing |
| `app/Agents/CoordinatingAgent.php` | `~/www/fynla.org/public_html/app/Agents/CoordinatingAgent.php` | Expanded handlers for property (all fields), pension (DC+DB), chattel (spelling fix), savings (enum fix), expenditure (direct save), liabilities (enum fix), protection (term→level_term mapping, FIB benefit_amount), business (enriched with industry/revenue/dividends/employees), goals (enum update, custom_goal_type_name, message field). Null sanitisation, HTML entity decode, required field defaults. |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Compiled into `public/build/` | AI fill watcher expanded (32 highlight bindings), property_type early-set fix, scroll error fix |
| `resources/js/components/Retirement/DBPensionForm.vue` | Compiled into `public/build/` | Pre-set scheme_status, scheme_type, employer_name in pendingFill watcher |
| `resources/js/components/NetWorth/ChattelFormModal.vue` | Compiled into `public/build/` | Pre-set chattel_type and name in pendingFill watcher |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Compiled into `public/build/` | AI fill watchers (Composition API) for expenditure categories |
| `resources/js/views/ValuableInfo.vue` | Compiled into `public/build/` | Watch pendingFill to switch to expenditure tab |
| `resources/js/components/Protection/PolicyFormModal.vue` | Compiled into `public/build/` | Added `family_income_benefit` to life_policy_type dropdown. Filling watcher with $nextTick, 500ms delay, error reporting to chat. |
| `resources/js/components/NetWorth/BusinessInterestForm.vue` | Compiled into `public/build/` | Pre-set business_name, business_type, current_valuation in pendingFill watcher. Filling watcher with $nextTick, 500ms delay, error reporting to chat. |
| `resources/js/components/Goals/GoalFormModal.vue` | Compiled into `public/build/` | Pre-set goal_name, goal_type, target_amount, target_date, custom_goal_type_name in pendingFill. Filling watcher with $nextTick, 500ms, error reporting. |
| `resources/js/views/Goals/GoalsDashboard.vue` | Compiled into `public/build/` | Fixed cancelFill→completeFill so "Done" confirmation appears in chat after goal save. |
| `resources/js/components/Goals/LifeEventForm.vue` | Compiled into `public/build/` | Pre-set event_name, event_type, amount, expected_date in pendingFill. Filling watcher with $nextTick, 500ms, error reporting. |
| `resources/js/components/Goals/EventsTab.vue` | Compiled into `public/build/` | Fixed cancelFill→completeFill for "Done" chat confirmation after life event save. |

### Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` to server.

## Deploy Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload to server via SiteGround File Manager:**
   - `public/build/` → `~/www/fynla.org/public_html/public/build/`
   - `app/Services/AI/XaiToolDefinitions.php` (new file)
   - `app/Traits/HasAiChat.php`
   - `app/Agents/CoordinatingAgent.php`

3. **SSH and clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```

4. **Set AI provider (if switching to xAI on production):**
   - Either set `AI_PROVIDER=xai` in `.env` on server
   - Or use the admin panel AI toggle (instant, no SSH needed)

## .env Variables Required for xAI

```
AI_PROVIDER=xai
XAI_API_KEY=xai-xxxxxxxxxxxx
```

These must be set on the server if switching provider. The admin panel toggle overrides `AI_PROVIDER` via cache.

## No Migrations

No database changes. No seeding required.

## Test Verification — Property (5/5 PASS)

| Scenario | Type | Ownership | Mortgage | Result |
|----------|------|-----------|----------|--------|
| A | Main residence | Individual 100% | None | PASS |
| B | Main residence | Joint 50% | £300k Halifax repayment 4.2% fixed £1600/mo | PASS |
| C | Secondary residence | TiC 70/30 | £150k Nationwide interest-only 3.8% variable £475/mo | PASS |
| D | Buy-to-let | Joint 50% | £160k Barclays repayment 5.1% fixed £950/mo + tenant | PASS |
| E | Leasehold flat | Individual 100% | None + monthly costs (council tax, service charge, insurance) | PASS |

## Test Verification — Pensions (6/6 PASS)

| Scenario | Category | Type | Provider | Value | Result |
|----------|----------|------|----------|-------|--------|
| 1 | DC | Workplace | Scottish Widows | £85,000 (5%/3%, £55k salary) | PASS |
| 2 | DC | SIPP | Hargreaves Lansdown | £120,000 (£500/mo, age 60) | PASS |
| 3 | DC | Personal | Standard Life | £45,000 (£200/mo) | PASS |
| 4 | DC | Stakeholder | Legal & General | £18,000 (£150/mo) | PASS |
| 5 | DB | Final Salary | NHS | £12,000/yr (15 years) | PASS |
| 6 | DB | Career Average (Deferred) | Teachers' | £6,500/yr (8 years) | PASS |

## Test Verification — Chattels (6/6 PASS)

| Scenario | Type | Item | Value | Purchase | Result |
|----------|------|------|-------|----------|--------|
| 1 | Collectible | Vintage Rolex Submariner watch | £15,000 | £8,000 | PASS |
| 2 | Vehicle | 2022 BMW M5 | £65,000 | £78,000 | PASS |
| 3 | Art | Original Banksy print | £25,000 | £3,000 | PASS |
| 4 | Antique | Georgian mahogany bureau | £4,500 | £2,800 | PASS |
| 5 | Jewellery | Diamond engagement ring | £7,000 | £5,500 | PASS |
| 6 | Other/Collectible | Case of fine wine | £3,000 | £1,800 | PASS |

## Test Verification — Cash/Savings (5/5 PASS)

| Scenario | Type | Institution | Balance | Rate | Flags | Result |
|----------|------|------------|---------|------|-------|--------|
| 1 | Easy Access | Marcus by Goldman Sachs | £15,000 | 4.5% | — | PASS |
| 2 | Cash ISA | Nationwide | £18,500 | 4.1% | ISA, £500/mo contribution | PASS |
| 3 | Premium Bonds | NS&I | £5,000 | 0% | Correct NS&I section | PASS |
| 4 | Emergency Fund | Barclays | £10,000 | 3.8% | Emergency fund flag set | PASS |
| 5 | Current Account | HSBC | £3,200 | 0% | Correct section | PASS |

## Test Verification — Expenditure (1/1 PASS)

| Scenario | Categories | Total | Method | Result |
|----------|-----------|-------|--------|--------|
| 1 | Food £400, transport £150, mobile £45, internet £60, subs £30, clothes £100, dining £200, holidays £250 | £1,235/mo | Direct DB save via update_profile redirect | PASS |

## Test Verification — Liabilities (8/8 PASS)

| Scenario | Type | Name | Balance | Payment | Rate | Result |
|----------|------|------|---------|---------|------|--------|
| 1 | Credit Card | Barclays credit card | £3,500 | £150/mo | 19.90% | PASS |
| 2 | Personal Loan | Halifax Personal Loan | £8,000 | £250/mo | 6.50% | PASS |
| 3 | Student Loan | Plan 2 Student Loan | £28,000 | — | — | PASS |
| 4 | Hire Purchase | BMW Car Finance | £12,000 | £350/mo | 4.90% | PASS |
| 5 | Secured Loan | Lloyds Secured Loan | £15,000 | £280/mo | 7.20% | PASS |
| 6 | Overdraft | HSBC current account overdraft | £2,000 | — | — | PASS |
| 7 | Business Loan | NatWest Business Loan | £25,000 | £500/mo | 8.50% | PASS |
| 8 | Other (Personal Loan) | Loan from Brother | £5,000 | — | — | PASS |

## Test Verification — Protection (8/8 PASS)

| Scenario | Type | Provider | Amount | Key Fields | Result |
|----------|------|----------|--------|------------|--------|
| 1 | Level Term Life | Aviva | £500,000 | 25yr, in trust | PASS |
| 2 | Whole of Life | Royal London | £200,000 | in trust, no term | PASS |
| 3 | Standalone CI | Vitality | £150,000 | 20yr term | PASS |
| 4 | Income Protection | LV= | £2,500/mo benefit | monthly benefit | PASS |
| 5 | Family Income Benefit | Zurich | £3,000/mo benefit | 18yr, benefit mapped correctly | PASS |
| 6 | Decreasing Term | Legal & General | £350,000 | 20yr, mortgage protection | PASS |
| 7 | Accelerated CI | Scottish Widows | £250,000 | 25yr term | PASS |
| 8 | Term Life (generic) | AIG | £300,000 | 15yr, mapped to level_term | PASS |

## Test Verification — Business Interests (4/4 PASS)

| Scenario | Type | Name | Value | Key Fields | Result |
|----------|------|------|-------|------------|--------|
| 1 | Sole Trader | Smith Consulting | £150,000 | Consulting sector, £60k profit | PASS |
| 2 | Limited Company | Acme Technologies Ltd | £500,000 | Tech, £250k rev, £80k profit, £40k divs | PASS |
| 3 | Partnership | Jones & Partners | £300,000 (50%) | Law, 50% ownership, £400k rev | PASS |
| 4 | LLP | Digital Solutions LLP | £200,000 (33%) | IT consultancy, 33% share, £90k profit | PASS |

## Test Verification — Goals (9/9 PASS)

| Scenario | Type | Name | Target | Priority | Module | Result |
|----------|------|------|--------|----------|--------|--------|
| 1 | emergency_fund | Emergency Fund | £10,000 | Critical | Savings | PASS |
| 2 | home_deposit | House Deposit | £50,000 | High | Property | PASS |
| 3 | holiday | Family Holiday | £5,000 | Low | Savings | PASS |
| 4 | wedding | Wedding Fund | £20,000 | High | Savings | PASS |
| 5 | car_purchase | New Car Fund | £15,000 | Medium | Savings | PASS |
| 6 | education | Daughter's Uni Fees | £30,000 | High | Investment | PASS |
| 7 | debt_repayment | Pay off Credit Card | £8,000 | Critical | Savings | PASS |
| 8 | wealth_accumulation | Investment Portfolio | £100,000 | Medium | Investment | PASS |
| 9 | custom | New Home Office Setup | £3,000 | Low | Custom | PASS |

## Test Verification — Life Events (16/16 PASS)

| Scenario | Type | Name | Amount | Certainty | Result |
|----------|------|------|--------|-----------|--------|
| 1 | inheritance | Parents' Estate Inheritance | +£150,000 | Likely | PASS |
| 2 | home_improvement | Kitchen Renovation | -£25,000 | Confirmed | PASS |
| 3 | bonus | Work Bonus | +£10,000 | Confirmed | PASS |
| 4 | large_purchase | Boat Purchase | -£40,000 | Speculative | PASS |
| 5 | gift_received | Grandmother's Gift | +£20,000 | Confirmed | PASS |
| 6 | redundancy_payment | Redundancy Payment | +£35,000 | Possible | PASS |
| 7 | property_sale | Buy-to-Let Sale | +£280,000 | Likely | PASS (Grok also created property record) |
| 8 | business_sale | Consulting Business Sale | +£200,000 | Likely | PASS |
| 9 | pension_lump_sum | Tax-Free Pension Lump Sum | +£50,000 | Confirmed | PASS |
| 10 | lottery_windfall | Premium Bonds Win | +£5,000 | Confirmed | PASS |
| 11 | custom_income | Car Accident Insurance Payout | +£8,000 | Likely | PASS |
| 12 | wedding | Daughter's Wedding | -£15,000 | Confirmed | PASS |
| 13 | education_fees | Son's School Fees | -£12,000 | Confirmed | PASS |
| 14 | gift_given | Nephew's Wedding Gift | -£10,000 | Confirmed | PASS |
| 15 | medical_expense | Dental Implants | -£6,000 | Confirmed | PASS |
| 16 | custom_expense | Garden Landscaping | -£3,000 | Possible | PASS (Grok chose home_improvement) |

## Known Issues

- **DB pension API field mapping**: The DB pension form uses field names (`employer_name`, `annual_income`, `service_years`) that don't match the API validation/DB columns (`scheme_name`, `accrued_annual_pension`, `pensionable_service_years`). Pre-existing bug — same on manual form submit. Form fills and displays correctly but some fields don't persist to DB.

- **Expenditure form fill pattern**: Expenditure uses direct DB save instead of the form fill pattern due to the inline edit form's Composition API `initializeFromProps` race condition. Data saves correctly to all categories but doesn't visually animate through the form. Needs further work to match other modules' form fill UX.

- **xAI HTML entity encoding**: xAI sometimes returns HTML entities in tool arguments (e.g. `NS&amp;I`). Fixed with `html_entity_decode()` in `executeTool()`.

- **Protection TypeError**: `TypeError: Cannot convert undefined or null to object` at PolicyFormModal.vue:196 during every AI fill. Does NOT block save — console error only. Needs null guard investigation.

## Key Technical Details

- **XaiToolDefinitions.php** returns pre-wrapped OpenAI function format with `strict: true`
- Nullable enums use `anyOf` pattern (strict mode requirement)
- 3 tools without strict mode: `create_what_if_scenario`, `update_record`, `update_profile` (dynamic key-value objects)
- xAI returns string `"null"` instead of JSON `null` for nullable fields — sanitised in `executeTool()`
- xAI returns HTML entities in tool arguments — decoded with `html_entity_decode()` in `executeTool()`
- `property_type` must be set early in `pendingFill` watcher before field sequence starts (Vue select reactivity)
- DB pension `scheme_status` and `scheme_type` pre-set in `pendingFill` watcher (same Vue select fix)
- Chattel `chattel_type` and `name` pre-set in `pendingFill` watcher
- Protection `policyType` and `life_policy_type` pre-set in `pendingFill` watcher
- Business `business_name`, `business_type`, `current_valuation` pre-set in `pendingFill` watcher
- Goals `goal_name`, `goal_type`, `target_amount`, `target_date`, `custom_goal_type_name` pre-set in `pendingFill` watcher
- Goals: `custom` goal_type auto-sets `custom_goal_type_name` = goal name (backend requires it)
- Goals: GoalsDashboard fixed `cancelFill` → `completeFill` for "Done" chat confirmation
- Goals tool enum updated to match backend: `home_deposit`, `property_purchase`, `car_purchase`, `retirement`, `wealth_accumulation`, `debt_repayment`, `custom`
- Life Events: tool rewritten with full 16-type enum (9 income + 7 expense), `event_name` param, `certainty` param, `estimated_amount` param
- Life Events: handler maps `event_name`→`event_name`, `estimated_amount`→`amount`, `event_date`→`expected_date`
- Life Events: EventsTab fixed `cancelFill` → `completeFill` for "Done" chat confirmation
- Life Events: LifeEventForm pre-sets `event_name`, `event_type`, `amount`, `expected_date` in pendingFill watcher
- Protection handler maps generic `term` → `level_term` for life_policy_type dropdown
- Protection FIB uses `benefit_amount` → `coverage_amount` (same as income_protection)
- Business tool enriched with industry_sector, annual_revenue, annual_dividend_income, employee_count
- System prompt instructs Grok to call creation tools immediately, not ask questions first
- Tool descriptions prevent multi-tool calls in same turn (avoids page navigation interrupting form fill)
- `update_profile` with `section='expenditure'` redirects to `handleSetExpenditure` (direct save)
- Liability tool enum includes all 8 types: personal_loan, credit_card, student_loan, hire_purchase, secured_loan, overdraft, business_loan, other
- Savings tool enum includes all 10 types: savings_account, current_account, easy_access, instant_access, notice, fixed, cash_isa, junior_isa, premium_bonds, nsi
- Chattel `jewellery` (British) → `jewelry` (American) spelling mapping
- Anthropic path completely untouched — no regression risk

## Test Verification — Investment Holdings (1/1 PASS)

| Scenario | Security | Ticker | Asset Type | Allocation | Account | Result |
|----------|----------|--------|-----------|------------|---------|--------|
| 1 | iShares Core MSCI World ETF | SWDA | ETF | 30% | Hargreaves Lansdown ISA | PASS |

**Notes:**
- New `create_holding` tool added — looks up existing account by name/provider, navigates into account detail, opens HoldingForm
- Account lookup uses LIKE match on provider/account_name — picks most recent match if multiple
- Fund asset_type requires sub_type (backend validation) — ETF is safer for AI to use
- Manual test also passed: Vanguard FTSE All-World ETF, 50% allocation, 0.22% OCF in HL GIA

## Total Test Results: 69/69 PASS

| Module | Scenarios | Result |
|--------|-----------|--------|
| Property | 5 | 5/5 PASS |
| Pensions | 6 | 6/6 PASS |
| Chattels | 6 | 6/6 PASS |
| Cash/Savings | 5 | 5/5 PASS |
| Expenditure | 1 | 1/1 PASS |
| Liabilities | 8 | 8/8 PASS |
| Protection | 8 | 8/8 PASS |
| Business Interests | 4 | 4/4 PASS |
| Goals | 9 | 9/9 PASS |
| Life Events | 16 | 16/16 PASS |
| Investment Holdings | 1 | 1/1 PASS |
| **Total** | **69** | **69/69 PASS** |
