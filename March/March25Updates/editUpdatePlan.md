# AI Edit & Update — Implementation Plan

**Date:** 25 March 2026
**Branch:** `grokAI`
**Goal:** Ensure Grok AI can edit existing records, detect duplicates, and intelligently decide between create vs update.

## Problem Statement

Currently the AI creates new records for every user statement. If a user says "I put a lump sum into my Vanguard pension", the AI creates a NEW pension instead of updating the existing one. This is wrong — users expect the AI to recognise their existing data and modify it.

## Architecture Overview

```
User: "I put £5,000 lump sum into my Vanguard pension"
  ↓
AI needs to:
  1. Check if user already has a Vanguard pension → YES (ID 42)
  2. Call update_record instead of create_pension
  3. Frontend navigates to pension page, opens edit form for ID 42
  4. Pre-fills the lump sum field
  5. Saves
```

## Phase 1: Surface Existing Records in System Prompt (CRITICAL)

**Files:** `app/Traits/HasAiChat.php`

The system prompt already includes financial summaries but NOT individual record lists. The AI needs to know what already exists.

### 1a. Add record summary to system prompt

In `buildSystemPrompt()`, after the financial context section, add a new section listing the user's existing records with IDs:

```
<existing_records>
SAVINGS: [ID:12 "Marcus Easy Access" £15,000] [ID:14 "Nationwide Cash ISA" £18,500]
INVESTMENTS: [ID:5 "Vanguard ISA" £80,000 (3 holdings)] [ID:8 "HL GIA" £60,000]
PENSIONS: [ID:3 "Scottish Widows DC" £85,000] [ID:7 "NHS DB" £12,000/yr]
PROPERTIES: [ID:1 "42 Oak Lane" main_residence £450,000]
PROTECTION: [ID:2 "Aviva Level Term" life £500,000] [ID:5 "LV= IP" £2,500/mo]
TRUSTS: [ID:1 "Smith Family Trust" discretionary £350,000]
FAMILY: [Spouse: Jane Smith] [Child: Emma age 11] [Child: James age 16]
GOALS: [ID:1 "Emergency Fund" £10,000] [ID:3 "House Deposit" £50,000]
BUSINESS: [ID:1 "Acme Technologies Ltd" £500,000]
CHATTELS: [ID:2 "Vintage Rolex" £15,000]
GIFTS: [ID:1 "Emma" PET £50,000 Jun 2023]
LIABILITIES: [ID:3 "Barclays Visa" credit_card £3,500]
</existing_records>
```

This gives the AI enough context to match "my Vanguard pension" → ID 3.

### 1b. Add update-vs-create guidance to system prompt

Add to the `<available_actions>` section:

```
<updating_existing_records>
CRITICAL: Before creating ANY new financial record, check <existing_records> above.
- If the user mentions an account/policy/pension that ALREADY EXISTS → use update_record with the entity_id
- If the user says "I put money into", "I changed", "my X is now", "update my" → UPDATE, do not create
- If the user mentions something NOT in existing records → CREATE a new one
- If ambiguous (e.g. "my ISA" but they have 2 ISAs) → ASK which one they mean
- NEVER create a duplicate of an existing record

Examples:
- "I put £5,000 into my Vanguard pension" → update_record(dc_pension, ID:3, {lump_sum: 5000})
- "My Aviva life cover is now £600,000" → update_record(life_insurance, ID:2, {sum_assured: 600000})
- "I have a new ISA at Fidelity" → create_investment_account (NEW — not in existing records)
- "Change my Marcus savings rate to 4.8%" → update_record(savings_account, ID:12, {interest_rate: 4.8})
</updating_existing_records>
```

## Phase 2: Add List/Lookup Tools

**Files:** `app/Services/AI/XaiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`

Add lightweight list tools so the AI can actively query for records when the system prompt summary isn't enough:

### Tools to add:

| Tool | Returns | Use Case |
|------|---------|----------|
| `list_savings_accounts` | `[{id, account_name, institution, balance}]` | "Which savings account?" |
| `list_investment_accounts` | `[{id, provider, account_type, value}]` | "Which investment?" |
| `list_pensions` | `[{id, scheme_name, type, value}]` | "Which pension?" |
| `list_properties` | `[{id, address, type, value}]` | "Which property?" |
| `list_protection_policies` | `[{id, provider, type, cover}]` | "Which policy?" |
| `list_trusts` | `[{id, trust_name, type, value}]` | "Which trust?" |
| `list_business_interests` | `[{id, name, type, value}]` | "Which business?" |

These are read-only, lightweight, and return just enough for the AI to match user references to IDs.

**Implementation:** Single `handleListRecords($entityType, $user)` method that switches on type.

## Phase 3: Fix Broken Edit Flows

### 3a. Protection Policies

**Problem:** `PolicyFormModal.vue` has AI fill watchers but the parent Protection view doesn't watch for `fill.mode === 'edit'`.

**Fix:** In the Protection dashboard/view component, add:
```javascript
watch: {
  '$store.state.aiFormFill.pendingFill'(fill) {
    if (fill && ['life_insurance', 'critical_illness', 'income_protection'].includes(fill.entityType) && fill.mode === 'edit') {
      const policy = this.policies.find(p => p.id === fill.entityId);
      if (policy) this.openEditModal(policy);
    }
  }
}
```

Also add `mounted()` check.

### 3b. Trusts

**Status:** TrustsDashboard.vue already watches for `fill.mode === 'edit'` (verified in earlier session). Should work — needs testing.

### 3c. Savings Accounts (CashList.vue)

**Check:** Verify CashList.vue or SavingsList.vue has the edit mode watcher. If missing, add it.

### 3d. Gifts

**Status:** GiftingStrategy.vue watches for `fill.mode === 'edit'` (verified). The `GiftForm` receives the gift prop for edit. Should work — needs testing.

## Phase 4: Smart Create-vs-Update in Tool Descriptions

**Files:** `app/Services/AI/XaiToolDefinitions.php`

Update ALL creation tool descriptions to include:
```
"IMPORTANT: Before calling this tool, check <existing_records> in the system prompt.
If the user is referring to an existing record, use update_record instead."
```

Key tools to update:
- `create_investment_account` — "Check if user already has this ISA/GIA before creating"
- `create_pension` — "Check if this pension already exists before creating a new one"
- `create_savings_account` — "Check if this account already exists"
- `create_property` — "Check if this property is already recorded"
- `create_protection_policy` — "Check if this policy already exists"

## Phase 5: Test Matrix

### Edit tests (one per entity type):

| # | Entity | Test Prompt | Expected Action |
|---|--------|-------------|----------------|
| 1 | Savings | "Change my Marcus rate to 4.8%" | update_record savings_account |
| 2 | Investment | "My Vanguard ISA is now worth £90,000" | update_record investment_account |
| 3 | Pension (DC) | "I put a £5,000 lump sum into my pension" | update_record dc_pension |
| 4 | Property | "My house is now worth £500,000" | update_record property |
| 5 | Protection | "My Aviva life cover is now £600,000" | update_record life_insurance |
| 6 | Trust | "The Smith Family Trust is now worth £380,000" | update_record trust |
| 7 | Family | "Emma started secondary school" | update_record family_member |
| 8 | Goal | "I've saved £15,000 towards my house deposit now" | update_record goal |
| 9 | Business | "My company revenue is now £300,000" | update_record business_interest |
| 10 | Chattel | "My Rolex is now worth £18,000" | update_record chattel |
| 11 | Gift | "Actually that gift to Emma was £55,000 not £50,000" | update_record estate_gift |
| 12 | Liability | "I've paid my credit card down to £2,000" | update_record estate_liability |

### Duplicate detection tests:

| # | Test Prompt | Expected | Wrong |
|---|-------------|----------|-------|
| 1 | "I put £5,000 into my Vanguard pension" | Update existing pension | Create new pension |
| 2 | "My Marcus savings rate changed to 4.8%" | Update existing account | Create new account |
| 3 | "I have a new ISA at Fidelity" | Create new (different provider) | Update Vanguard ISA |
| 4 | "My house value has gone up to £500,000" | Update existing property | Create new property |

## Implementation Order

1. **Phase 1a** — Surface existing records in system prompt (1-2 hours)
2. **Phase 1b** — Add update-vs-create guidance (30 mins)
3. **Phase 2** — Add list tools (1-2 hours)
4. **Phase 3** — Fix protection edit flow (30 mins)
5. **Phase 4** — Update creation tool descriptions (30 mins)
6. **Phase 5** — Test all 12 edit scenarios + 4 duplicate detection (2-3 hours)

## Files to Change

### PHP
- `app/Traits/HasAiChat.php` — system prompt with existing records + edit guidance
- `app/Services/AI/XaiToolDefinitions.php` — list tools + creation tool description updates
- `app/Agents/CoordinatingAgent.php` — list record handlers

### Frontend
- Protection view component — add edit mode watcher
- Verify: CashList.vue, GiftingStrategy.vue edit flows

## Success Criteria

1. User says "update my X" → AI updates the correct existing record
2. User says "I put money into my Y" → AI updates, doesn't create duplicate
3. User says "I have a NEW Z" → AI creates (correct — new item)
4. User says "my X" but has 2 → AI asks which one
5. All 18 entity types editable via AI
6. All 12 edit test scenarios pass
7. All 4 duplicate detection tests pass
