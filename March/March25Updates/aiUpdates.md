# AI Updates — 25 March 2026

**Branch:** `grokAI`

All work in this session: investment accounts (14 types), trusts (9 types), family members (6 types) — tested end-to-end with Grok AI via Fyn chat.

## 1. Investment Accounts — 14 Types Tested

All 14 investment account types tested with inline holdings where applicable.

### Bugs found and fixed

| Bug | Fix | Files |
|-----|-----|-------|
| SAYE: `units_granted` required but AI doesn't always send it | Made optional for SAYE (frontend + backend) | `AccountForm.vue`, `StoreInvestmentAccountRequest.php` |
| SAYE: `grant_date` required but AI sends `scheme_start_date` | Auto-populate from `scheme_start_date` for SAYE | `AccountForm.vue`, `StoreInvestmentAccountRequest.php` |
| Private Co/Crowdfunding cards show "Valuation: £0" | Added `current_value` fallback in `getDisplayValue()` | `InvestmentList.vue` |
| Wine/art routed to investment instead of chattel | Updated tool descriptions | `XaiToolDefinitions.php` |

## 2. Trusts — 9 Types Tested

All 9 UK trust types: discretionary, bare, interest_in_possession, life_insurance, discounted_gift, loan, accumulation_maintenance, mixed, settlor_interested.

### New features

- **Family name resolution (`resolveFamilyNames`):** Resolves "my wife" → "Jane Smith", "myself" → "John Smith", "my children" → actual names or placeholder, "my solicitor" → "(Solicitor) name to be confirmed". 16/16 unit tests passing.
- **Settlor auto-population:** Defaults to user's full name when user says "I have/created a trust".
- **Settlor form field:** Added to `TrustFormModal.vue` for manual editing.

### Bug fixed

| Bug | Fix | Files |
|-----|-----|-------|
| `trust_name` empty on AI fill (timing issue) | Pre-set trust_name/trust_type in pendingFill with `$nextTick` | `TrustFormModal.vue` |

## 3. Family Members — 6 Types Tested

Child, son (further education), parent, step child, other dependent, baby.

### New features

- Enriched tool: 6 relationship types (spouse, partner, child, step_child, parent, other_dependent)
- Child-specific fields: education_status, receives_child_benefit
- Surname defaults to user's surname when not mentioned
- Gender inferred from context (daughter=female, son=male)

### Bug found and fixed

| Bug | Fix | Files |
|-----|-----|-------|
| `step_child` and `partner` not in DB enum | Handler maps step_child→child, partner→other_dependent with note | `CoordinatingAgent.php` |
| pendingFill watcher not firing on mount | Added `{ immediate: true }` | `FamilyMemberFormModal.vue` |

## 4. Estate Gifts — 5 Types Tested

PET, annual exemption, exempt (charity), small gift, CLT.

### New features
- Enriched tool description with gift type guidance and date calculation hints
- Recipient name resolved via `resolveFamilyNames`
- **Automatic CLT gift on trust creation:** When `handleCreateTrust` creates a trust with initial_value > 0, a Chargeable Lifetime Transfer gift is automatically saved to DB. This ensures the 7-year rule and taper relief are tracked for IHT. Without this, creating a trust without a corresponding CLT gift would leave the estate plan inaccurate — the gift wouldn't appear in the gifting timeline and IHT calculations would be wrong.

### Bugs fixed

| Bug | Fix | Files |
|-----|-----|-------|
| GiftForm didn't open on AI fill | Added `mounted()` pendingFill check in GiftingStrategy | `GiftingStrategy.vue` |
| `gift_date` empty on AI fill | Pre-set fields in pendingFill with `$nextTick` | `GiftForm.vue` |
| CLT not recorded when trust created | Auto-create CLT gift in `handleCreateTrust` when initial_value > 0 | `CoordinatingAgent.php` |

## 5. AI Edit & Update — Phase 1 (System Prompt)

**Critical change:** The AI now knows about ALL existing records before deciding to create or update.

### What changed
- `buildExistingRecordsSummary()` added to `HasAiChat.php` — queries all 13 entity types and builds a compact `[ID:X "Name" type £value]` summary
- `<existing_records>` section added to the system prompt between `<financial_context>` and `<data_completeness>`
- Update-vs-create guidance added to `<available_actions>` — tells AI to check existing records first, use `update_record` for modifications, never create duplicates

### Entity types covered
Savings, investments, DC pensions, DB pensions, properties, life insurance, critical illness, income protection, trusts, business interests, chattels, liabilities, gifts, family members

### Example output
```
INVESTMENTS: [ID:18 "Vanguard" isa £80,000] [ID:19 "Interactive Investor" gia £60,000]
DC PENSIONS: [ID:3 "Scottish Widows" workplace £85,000]
TRUSTS: [ID:17 "Smith Family Trust" discretionary £350,000]
FAMILY: [Spouse: Jane Smith] [ID:21 "Emma Smith" child age 11]
```

### Phase 2: list_records tool (DONE)
- New `list_records` tool added — 14 entity types, returns ID + key fields
- Single `handleListRecords()` handler in CoordinatingAgent
- AI can actively query for records when system prompt cache is stale

### Phase 3: Protection policy edit flow (DONE)
- `ProtectionDashboard.vue` — implemented `findPolicyById()` method, edit mode watcher now finds policy across all types (life, CI, IP, disability, sickness)
- Both `watch` and `mounted()` check handle edit fills

### Phase 4: Creation tool duplicate warnings (DONE)
- Added `DUPLICATE PREVENTION` rule to `<data_creation_guidance>` in system prompt
- AI checks `<existing_records>` before calling any creation tool

### Phase 5: Edit persistence bug fix + Testing

**Bug found:** `InvestmentList.vue` line 453 called `updateAccount({ id, data })` but the Vuex action expected `{ id, accountData }`. The `accountData` parameter was `undefined`, so the API received no data. Fixed: `{ id, accountData: data }`.

**Test 1 — Duplicate detection + edit persistence: PASS**
- Prompt: "My Vanguard ISA is now worth £95,000"
- AI identified existing record ID:18 from `<existing_records>`
- Called `update_record` (not `create_investment_account`)
- Edit form opened pre-populated with existing data
- AI fill set `current_value = 95000`
- Auto-submitted → PUT /api/investment/accounts/18
- DB confirmed: £80,000 → £95,000
- Card updated to £95,000, portfolio total recalculated to £586,350

### Remaining (next session)
- Test remaining 11 edit scenarios across all entity types
- Test 3 more duplicate detection scenarios
- Verify edit persistence works for pensions, properties, trusts, family members etc

## 6. Navigation Audit

Updated `navigate_to_page` tool with comprehensive route descriptions covering all application pages.

### What changed
- `XaiToolDefinitions.php` — expanded route_path description with investment detail views (`/net-worth/fees-detail`, `/net-worth/holdings-detail`, `/net-worth/investment-detail`, `/net-worth/tax-efficiency`, `/net-worth/strategy-detail`), savings dashboard (`/savings`, `/savings/account/{id}`), and improved descriptions for income, expenditure, goals, life events, estate, and all other sections

### Test results — 6/6 PASS
- Income → `/valuable-info?section=income` PASS
- Expenditure → `/valuable-info?section=expenditure` PASS
- Goals → `/goals` PASS
- Life Events → `/goals?tab=events` PASS
- Investment fees → `/net-worth/investments` PASS (fees-detail route now available for future)
- Savings analysis → `/net-worth/cash` PASS

## 7. Other Fixes (non-AI)

### WARN-002: Sessions API 500
- Added `->whereHas('token')` to filter orphaned sessions
- Wrapped controller in try-catch

### WARN-003: Holistic plan "Cannot read properties of undefined"
- Added `&& plan.current_situation` to v-if guards

### Test user access
- `TestUsersSeeder` now creates `Subscription` records with trial so test users have full app access

## All Files Changed

### PHP

```
app/Traits/HasAiChat.php
app/Services/AI/XaiToolDefinitions.php
app/Agents/CoordinatingAgent.php
app/Http/Requests/StoreInvestmentAccountRequest.php
app/Http/Controllers/Api/Estate/TrustController.php
app/Http/Controllers/Api/SessionController.php
app/Services/Auth/SessionService.php
database/seeders/TestUsersSeeder.php
```

### Frontend (rebuild required)

```
resources/js/components/Investment/AccountForm.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/Plans/Holistic/HolisticPlanContent.vue
resources/js/components/Trusts/TrustFormModal.vue
resources/js/components/UserProfile/FamilyMemberFormModal.vue
resources/js/components/Estate/GiftForm.vue
resources/js/components/Estate/GiftingStrategy.vue
resources/js/views/Protection/ProtectionDashboard.vue
```

### Documentation

```
March/March24Updates/AI/investment-holding-form-algorithm.md (updated)
March/March24Updates/AI/trust-form-algorithm.md (new)
March/March24Updates/AI/family-member-form-algorithm.md (new)
March/March24Updates/AI/gift-form-algorithm.md (new)
March/March24Updates/deployAI.md (updated — 104/104 tests)
March/March25Updates/editUpdatePlan.md (new — edit/update implementation plan)
```

## Deploy Steps

1. Run `./deploy/fynla-org/build.sh` locally
2. Upload `public/build/` to server
3. Upload the 8 PHP files above
4. SSH and clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Test Summary — 104/104 PASS

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
| Investment Accounts (14 types) | 14 | 14/14 PASS |
| Investment Holdings (standalone) | 1 | 1/1 PASS |
| Trusts (9 types) | 9 | 9/9 PASS |
| Family Members (6 types) | 6 | 6/6 PASS |
| Estate Gifts (5 types) | 5 | 5/5 PASS |
| Edit/Update (duplicate detection) | 1 | 1/1 PASS |
| **Total** | **104** | **104/104 PASS** |
