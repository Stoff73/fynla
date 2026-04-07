# Patch Notes — 7 April 2026

**Version**: v0.9.4 patch
**Commits**: 24 (`228b38f..0d7134e`)
**Files changed**: 31 (+1,827 / -399 lines)

---

## Bug Fixes

### BUG-1: Personal Allowance Taper (Critical)

**Impact**: Tax understated by ~£5,656 for anyone earning above £125,140.

The detailed tax breakdown on the income page was not applying the Personal Allowance taper. The allowances section correctly showed PA = £0, but the tax band calculation still gave the full £12,570 at 0%. Fixed by tapering the PA in `UKTaxCalculator::calculateDetailedNetIncome()` before creating `TaxBandTracker`.

**Affected**: Alex Chen (£180k), David Mitchell (£159k), Sarah Mitchell (£164k), and any real user with income above £125,140.

**Files**: `app/Services/UKTaxCalculator.php`

---

### BUG-2: Investment Account Delete Fails Silently

Investment deletion showed a confirmation dialog, user clicked confirm, dialog dismissed, but the account remained. The error was being swallowed silently. Fixed by cascading soft-delete to holdings before deleting the account, and surfacing errors to the user.

**Files**: `app/Http/Controllers/Api/InvestmentController.php`, `resources/js/components/NetWorth/InvestmentProjections.vue`

---

### BUG-3: Property Delete Doesn't Cascade to Mortgage

Deleting a property left orphaned mortgages inflating liabilities and corrupting net worth. The database has `ON DELETE CASCADE` on the foreign key, but soft-delete doesn't trigger SQL CASCADE. Fixed by explicitly soft-deleting associated mortgages before the property.

**Files**: `app/Http/Controllers/Api/PropertyController.php`

---

### BUG-5: Fyn AI Can't Retrieve Mortgage Interest Rate

Fyn said "no interest rate recorded" when asked about mortgage rates, despite the data existing on the property page. The `list_records` tool had no `mortgage` case. Fixed by adding a mortgage handler to `CoordinatingAgent::handleListRecords()` and adding `mortgage` to the tool definition enum.

**Files**: `app/Agents/CoordinatingAgent.php`, `app/Services/AI/XaiToolDefinitions.php`

---

### BUG-6: Fyn AI References "2025/26" Instead of "2026/27"

The tax year in the compliance rules was hardcoded as "2025/26". Fixed by making it dynamic from `TaxConfigService::getTaxYear()`.

**Files**: `app/Services/AI/Prompts/ComplianceRules.php`, `app/Services/AI/SystemPromptBuilder.php`

---

### BUG-7: Joint Badge Contradicts 100% Ownership

The investments page showed a "Joint" badge on accounts where ownership was 100%. Fixed by adding a guard: badge only shows when `ownership_percentage < 100`.

**Files**: `resources/js/components/NetWorth/InvestmentList.vue`

---

### BUG-10: Alex Chen Missing Dividend Income

Preview persona Alex Chen was missing £60,000 dividend income specified in the reference data. Added the field to the persona JSON and expanded the seeder's income field mapping to include `annual_dividend_income`, `annual_self_employment_income`, `annual_rental_income`, and `annual_interest_income`.

**Files**: `resources/js/data/personas/entrepreneur.json`, `database/seeders/PreviewUserSeeder.php`

---

### Estate Planning Card Empty on Dashboard

The estate planning card showed an empty state ("Add your assets...") when a user had no IHT liability, even if they had estate data. Fixed in two steps:

1. Removed the empty state entirely — the card either shows with data or doesn't show at all.
2. Broadened `hasEstateData` to check `grossEstate > 0` (total estate value) in addition to `taxableEstate` and `ihtLiability`. Users whose estate falls within the Nil Rate Band / Residence Nil Rate Band now see their estate card correctly.

**Files**: `resources/js/views/Dashboard.vue`

---

### Spouse/Family Member Dropdown

The "Spouse" option in the Add Family Member dropdown was hidden when marital status was "single". Spouse (and Partner) are now always visible regardless of current marital status.

**Files**: `resources/js/components/UserProfile/FamilyMemberFormModal.vue`

---

### Marital Status Auto-Transitions

Previously no automatic updates to marital status when adding or removing a spouse. Now:

- **Adding a spouse**: Automatically updates marital status to "married" from single, divorced, or widowed.
- **Removing a spouse**: Prompts user with three options — "Divorced", "Widowed", or "Keep current status".

**Transitions**: single→married, divorced→married, widowed→married, married→divorced, married→widowed.

**Files**: `resources/js/components/UserProfile/FamilyMembers.vue`

---

## Fyn AI Improvements

### Joint Ownership Awareness

Fyn previously couldn't see joint ownership details. Now the system prompt (Layer 6) and tool results include:

- Co-owner name (from `joint_owner_name` field on the record)
- Ownership type (joint, tenants in common)
- Split percentages (e.g. 50%/50%)
- User's share values for property value, mortgage, and rental income
- Total values alongside user's share for clarity

**Example prompt**: `[ID:8 "19 Worth Court" buy_to_let joint with wife(50%/50%) mortgage-total:£3,500 your-mortgage:£1,750 rent-total:£900/mo your-rent:£450/mo total:£180,000 your-share:£90,000]`

Added `orWhere('joint_owner_id', $userId)` queries for business interests, chattels, and liabilities (were previously querying `user_id` only, missing records where the user is the joint owner).

**Files**: `app/Services/AI/SystemPromptBuilder.php`, `app/Agents/CoordinatingAgent.php`

---

### Family Names in User Profile (Layer 4)

System prompt Layer 4 now includes all family member names, relationships, and ages — not just a child count. Fyn can now reference family members by name naturally (e.g. "Emily's pension", "for Oliver and Sophie").

**Files**: `app/Services/AI/SystemPromptBuilder.php`

---

### xAI Prompt Caching

Enabled prompt caching via the `x-grok-conv-id` header. Routes all requests for the same conversation to the same xAI server, giving a 75% discount on cached input tokens ($0.05 vs $0.20 per 1M). Cached token counts logged in message metadata for monitoring.

**Files**: `app/Services/AI/XaiClient.php`, `app/Traits/HasAiChat.php`

---

### Temperature Set to 0.7

Previously defaulting to 1.0 (API default). Now set to 0.7 for more focused, reliable financial advice while keeping a natural conversational tone.

**Files**: `app/Traits/HasAiChat.php`

---

### Token Limits Overhauled

| Plan | Old Limit | New Limit |
|------|-----------|-----------|
| Preview | 10,000 | 50,000 |
| Trial | (missing) | 500,000 |
| Student | 50,000 | 150,000 |
| Standard | 200,000 | 500,000 |
| Family | (missing) | 500,000 |
| Pro | 500,000 | 1,000,000 |

Trial users now detected via `$subscription->isTrialing()` and get the same allowance as Standard.

**Files**: `app/Traits/HasAiGuardrails.php`

---

### Prompt Redundancy Cleanup (~440 tokens saved)

Removed duplicate instructions across prompt layers:

- "Use get_tax_information" — was stated 4 times, now once (Layer 2)
- "Don't mention irrelevant concepts" — was 3 times, now once (Layer 2)
- "No jargon" — was 3 times, now once (Layer 2)
- "Specific £ amounts" — was 3 times, now once (Layer 2)
- KNOWLEDGE_CAVEAT — removed entirely (redundant with Layer 2)
- AFFORDABILITY_RULES "WHEN GIVING" section — removed (duplicated Layer 2)
- DUPLICATE PREVENTION in data_creation_guidance — removed (already in available_actions)

**Files**: `app/Services/AI/Prompts/FcaProcessInstructions.php`, `app/Services/AI/Prompts/QueryKnowledge.php`, `app/Constants/FinancialPlanningKnowledge.php`

---

### Legacy Code Removed (~760 lines)

Deleted `buildSystemPromptLegacy()` and 9 duplicate helper methods from `HasAiChat.php`. System prompt assembly now handled exclusively by `SystemPromptBuilder`. File reduced from 1,451 to 690 lines.

**Files**: `app/Traits/HasAiChat.php`

---

## Documentation

- `April/April7Updates/fyn.md` — Complete Fyn AI system map (24 sections, 1,490 lines)
- `April/April7Updates/bugPlanFix.md` — Bug fix plan from QA + Brett test reports
- `April/April7Updates/estate-redesign-plan.md` — Estate dashboard redesign plan
- `April/April7Updates/spouse-lifecycle-plan.md` — Spouse lifecycle cross-module data visibility plan
- `CLAUDE.md` — Updated Vue component count to 656

---

## Browser Tested (Production — fynla.org)

| Test | Result |
|------|--------|
| PA taper: Alex Chen income page | PASS — PA = £0, correct tax |
| PA taper: David Mitchell income page | PASS — PA = £0, correct tax |
| Fyn AI: "What tax year?" | PASS — responds "2026/27" |
| Fyn AI: "What mortgage rate?" | PASS — returns 4.5% for 14 Maple Avenue |
| Fyn AI: "Which properties jointly owned?" | PASS — names co-owner, split, share values |
| Alex Chen dividend income | PASS — £60,000 showing |
| All 2,191 Pest tests | PASS |

---

## Cookie Consent Banner (New Feature)

### Cookie Preferences Overlay

A bottom-centre overlay card with backdrop that appears on first visit when no cookie preference is stored in localStorage. Two states:

**Initial state**: "We use cookies to help analyse how you use our site." with Accept Cookies (raspberry CTA) and Decline Cookies buttons. Links to Privacy Policy.

**Warning state** (after clicking Decline): "Without cookies, some features including registration will be unavailable. Google Analytics has been disabled." with Accept Cookies and Continue Without Cookies buttons.

Choice persists in `localStorage` key `cookie_consent` — banner never shows again once a decision is made.

**Files**: `resources/js/components/Shared/CookieBanner.vue`, `resources/js/App.vue`

---

### Google Analytics Gated Behind Consent

The hardcoded gtag script has been removed from `app.blade.php`. Google Analytics now only loads dynamically via `cookieConsent.js` when the user has explicitly accepted cookies. Plausible Analytics is unaffected — it's cookie-free and GDPR-compliant by design, so it continues to run regardless of consent.

**Files**: `resources/views/app.blade.php`, `resources/js/utils/cookieConsent.js`

---

### Registration Requires Cookie Consent

Users who have declined cookies (or not yet made a choice) see a "Cookies Required" card on the registration page instead of the form. Explains that cookies are needed to keep them securely signed in. An "Accept Cookies & Continue" button accepts cookies and reveals the registration form.

**Files**: `resources/js/views/Register.vue`

---

## Estate Planning Dashboard Redesign

### 3-Column Card Grid Layout

Replaced tab-based layout (IHT Planning / Gifting Strategy / Life Policy / Trusts) with a 3-column card grid. All cards visible at once — no tabs.

**Row 1**: Inheritance Tax Summary | Will | Power of Attorney
**Row 2**: Charitable Bequest | Life Policy | Gifting

**Files**: `resources/js/views/Estate/EstateDashboard.vue`, `resources/js/components/Estate/IHTPlanning.vue`

---

### IHT Calculation Table — Dedicated View

Full IHT calculation table moved to its own route at `/estate/inheritance-tax`. Clicking the IHT Summary card navigates to this view. Includes back button, full breakdown table, and tax allowance cards. Works for both married (joint death scenario) and single users.

**Files**: `resources/js/views/Estate/InheritanceTaxDetail.vue` (new), `resources/js/router/index.js`

---

### Card Content Improvements

- **Power of Attorney**: Shows each LPA type (Property & Financial, Health & Welfare) with its status instead of just "2 Registered"
- **Life Policy**: Cover needed and recommended shown inline. Shows "Joint Second Death" type for married users. Written in trust.
- **Gifting**: Shows annual exemption (£3,000), small gift allowance (£250 per person), and 7-year Potentially Exempt Transfer availability. Removed IHT liability (already on IHT Summary card).
- **Charitable Bequest**: Personalised minimum donation amount (e.g. "Leave £72,878+ to charity to reduce your Inheritance Tax rate?") instead of generic "10%+" text. Removed redundant explanation line when No is selected.

**Files**: `resources/js/components/Estate/IHTPlanning.vue`

---

### Life Events Impact — Simplified

- Removed 3 summary cards (incoming/outgoing/net impact)
- Removed timeline view (dots, lines)
- Removed icons from cards
- Removed projected liability text
- Removed review triggers section (duplicate of individual event card impacts)
- Kept individual event impact cards only (coloured green for incoming, red for outgoing)
- Hidden from IHT detail view (already shown on estate dashboard)

**Files**: `resources/js/components/Estate/EstateLifeEventsImpact.vue`

---

### Sidebar — Expression of Wishes / Letter to Spouse

The sidebar estate section dynamically switches between "Expression of Wishes" (no spouse) and "Letter to Spouse" (has spouse) based on the `spousePermission` store. Previously this only updated on page reload. Now it refreshes immediately when:

- Adding a spouse (single → married)
- Deleting a spouse (with marital status prompt)
- Updating marital status (married → divorced reverts to "Expression of Wishes"; married → widowed keeps "Letter to Spouse")

Widowed users now correctly keep "Letter to Spouse" — they had a spouse and may still have wishes to communicate. Previously widowed and divorced were both excluded.

**Files**: `resources/js/components/UserProfile/FamilyMembers.vue`, `resources/js/store/modules/spousePermission.js`

---

## Files Changed

| File | Category |
|------|----------|
| `app/Services/UKTaxCalculator.php` | Bug fix (PA taper) |
| `app/Http/Controllers/Api/PropertyController.php` | Bug fix (cascade delete) |
| `app/Http/Controllers/Api/InvestmentController.php` | Bug fix (delete cascade + error) |
| `app/Agents/CoordinatingAgent.php` | Fyn AI (mortgage tool + joint ownership) |
| `app/Services/AI/SystemPromptBuilder.php` | Fyn AI (family names, joint ownership, prompt cleanup) |
| `app/Services/AI/XaiClient.php` | Fyn AI (prompt caching) |
| `app/Services/AI/XaiToolDefinitions.php` | Fyn AI (mortgage enum) |
| `app/Services/AI/Prompts/ComplianceRules.php` | Fyn AI (dynamic tax year) |
| `app/Services/AI/Prompts/FcaProcessInstructions.php` | Fyn AI (redundancy cleanup) |
| `app/Services/AI/Prompts/QueryKnowledge.php` | Fyn AI (redundancy cleanup) |
| `app/Constants/FinancialPlanningKnowledge.php` | Fyn AI (redundancy cleanup) |
| `app/Traits/HasAiChat.php` | Fyn AI (caching, temperature, legacy removal) |
| `app/Traits/HasAiGuardrails.php` | Fyn AI (token limits, trial tier) |
| `resources/js/components/NetWorth/InvestmentList.vue` | Bug fix (joint badge) |
| `resources/js/components/NetWorth/InvestmentProjections.vue` | Bug fix (delete error) |
| `resources/js/components/UserProfile/FamilyMemberFormModal.vue` | Fix (spouse always visible) |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Feature (marital status transitions + spouse permission refresh) |
| `resources/js/store/modules/spousePermission.js` | Fix (widowed users keep spouse state for Letter to Spouse) |
| `CLAUDE.md` | Docs (Vue component count 651→656) |
| `resources/js/data/personas/entrepreneur.json` | Data (Alex Chen dividends) |
| `database/seeders/PreviewUserSeeder.php` | Data (income field mapping) |
| `resources/js/components/Shared/CookieBanner.vue` | Feature (cookie consent banner) |
| `resources/js/utils/cookieConsent.js` | Feature (GA gating, localStorage consent) |
| `resources/js/App.vue` | Feature (mount CookieBanner, init consent) |
| `resources/views/app.blade.php` | Feature (removed hardcoded GA script) |
| `resources/js/views/Register.vue` | Feature (registration consent gate) |
| `resources/js/views/Dashboard.vue` | Bug fix (estate card empty state + visibility) |
| `resources/js/views/Estate/EstateDashboard.vue` | Redesign (removed tabs, card grid) |
| `resources/js/views/Estate/InheritanceTaxDetail.vue` | New (IHT calculation detail view) |
| `resources/js/components/Estate/IHTPlanning.vue` | Redesign (3-col grid, card content, tableOnly prop) |
| `resources/js/components/Estate/EstateLifeEventsImpact.vue` | Redesign (simplified to event cards only) |
| `resources/js/router/index.js` | New route `/estate/inheritance-tax` |
