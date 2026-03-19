# 13 — AI CRUD Tools & Zero-Token Navigation

**PR #142 — aiTools → main**
**Date:** 19 March 2026 (session 2)

## Summary

Added full create/update/delete capabilities to the AI chat, plus a client-side navigation router that handles page navigation without calling the LLM.

## Changes

### New AI Tools

**4 new create tools (AiToolDefinitions.php):**
- `create_family_member`: spouse, child, parent, sibling, other — with DOB, gender, dependency
- `create_trust`: 7 trust types (discretionary, bare, IIP, life insurance, loan, discounted gift, A&M)
- `create_business_interest`: sole trader, partnership, limited company, LLP — with ownership %, value, profit
- `create_chattel`: jewellery, art, antiques, collectibles, vehicles — with value, insurance status

**Generic update tool:**
- `update_record`: updates any of 18 entity types by ID
- Validates entity belongs to user (`resolveModel()` with `user_id` check)
- Only allows fillable fields (blocks `user_id`/`id` changes)
- Entity types: goal, life_event, savings_account, investment_account, dc_pension, db_pension, property, mortgage, life_insurance, critical_illness, income_protection, estate_asset, estate_liability, estate_gift, family_member, trust, business_interest, chattel

**Generic delete tool:**
- `delete_record`: deletes any of 18 entity types by ID
- Validates ownership before deletion

**Profile update tool:**
- `update_profile`: 4 sections (personal, income_occupation, expenditure, domicile)
- Whitelisted fields per section for security
- Enables AI to bootstrap onboarding (set DOB, income, etc.)

### Zero-Token Navigation (chatNavigationRouter.js)

Client-side keyword matching in the AI chat input — handles navigation requests without calling the LLM.

**How it works:**
1. User types "show me my goals" or "go to estate planning"
2. `matchNavigationIntent()` checks for trigger phrase + route keyword
3. If matched: adds local messages to chat, navigates via Vue Router, returns immediately
4. If not matched: passes through to LLM normally

**25 routes covered** including dashboard, all net worth pages, income/expenditure, protection, estate, goals, plans, profile, settings.

**Trigger phrases:** "go to", "take me to", "show me", "open", "navigate to", "view", "see my", "look at", "check my", "find my"

## Files Changed

| File | Lines Changed |
|------|--------------|
| `app/Services/AI/AiToolDefinitions.php` | +151 |
| `app/Agents/CoordinatingAgent.php` | +265 |
| `app/Services/PrerequisiteGateService.php` | +4 |
| `resources/js/components/Shared/AiChatPanel.vue` | +23 |
| `resources/js/utils/chatNavigationRouter.js` | +92 (new file) |
