# Spouse Lifecycle — Cross-Module Data Visibility Plan

**Date**: 7 April 2026
**Status**: Planning (for next session)
**Priority**: High — affects all modules

---

## Problem

When a user's marital status changes (single → married → divorced / widowed), the application does not consistently update data visibility across all modules. Currently:

- Adding a spouse doesn't immediately surface their data in net worth, estate, protection, etc.
- Divorce doesn't remove spouse data visibility from module views
- The sidebar "Expression of Wishes" / "Letter to Spouse" label was the first fix (done this session), but the underlying data access issue is site-wide

## Rules

| Transition | What Should Happen |
|-----------|-------------------|
| **Single → Married** (spouse added) | Link accounts. Spouse data becomes visible across all modules. Joint assets show both owners. Net worth includes spouse view. Estate planning shows combined position. |
| **Married → Divorced** | Unlink spouse view access. Remove spouse data from all module views. Joint assets remain but ownership may need recalculating (tenants in common vs joint). Net worth reverts to individual only. Estate planning reverts to single. |
| **Married → Widowed** | Keep FULL access to spouse data and joint records. Deceased spouse's assets may need transferring. Estate planning needs to reflect inheritance. Do NOT remove any data visibility. |

## Audit Required — Modules That Use Spouse Data

### 1. Net Worth / Wealth Summary
- Shows combined net worth for married users
- Joint asset splits (properties, investments, savings)
- Needs: Hide spouse column on divorce, keep on widowed

### 2. Properties
- Joint properties with `joint_owner_id`
- Mortgage splits
- Needs: On divorce, ownership may change (joint → tenants in common or individual)

### 3. Investments
- Joint investment accounts
- Portfolio view includes spouse holdings
- Needs: Remove spouse holdings visibility on divorce

### 4. Savings / Cash
- Joint savings accounts
- Emergency fund calculations may include household
- Needs: Revert to individual on divorce

### 5. Retirement
- Spouse pension considerations
- State pension inheritance (widowed)
- Needs: Remove spouse pension view on divorce, keep on widowed

### 6. Estate Planning
- Combined estate value (second death scenario)
- Spouse exemption calculations
- NRB/RNRB transferability
- Needs: Revert to individual estate on divorce, recalculate IHT

### 7. Protection
- Joint life policies
- Coverage gap calculations include family
- Needs: Recalculate on divorce

### 8. Goals & Life Events
- Joint goals
- Shared life events
- Needs: Unlink shared goals on divorce

### 9. Income & Expenditure
- Household income (combined view)
- Shared expenditure
- Needs: Revert to individual on divorce

### 10. Dashboard
- Net worth widget (combined vs individual)
- Estate planning card
- Protection card
- Needs: All revert to individual on divorce

### 11. Sidebar Navigation
- "Letter to Spouse" / "Expression of Wishes" — DONE this session
- "Family" / "Personal Affairs" section label
- "View as [Spouse]" toggle

### 12. Fyn AI
- System prompt Layer 4 includes family members
- Layer 6 existing records include joint assets
- Tool results include co-owner name and share
- Needs: Reflect current marital status in context

## Files to Investigate

### Backend — Data Queries

| Area | Files to Check |
|------|---------------|
| Net Worth | `app/Services/NetWorth/NetWorthService.php` |
| Estate IHT | `app/Services/Estate/IHTCalculator.php`, `EstateAgent.php` |
| Protection | `app/Services/Protection/CoverageGapAnalyzer.php` |
| Retirement | `app/Services/Retirement/PensionProjector.php` |
| Spouse Permission | `app/Http/Middleware/SpousePermission.php`, `app/Services/SpousePermissionService.php` |
| All controllers | Any controller using `forUserOrJoint()` scope or `joint_owner_id` queries |

### Frontend — Module Views

| Area | Files to Check |
|------|---------------|
| Net Worth | `resources/js/views/NetWorth/WealthSummary.vue` |
| Dashboard | `resources/js/views/Dashboard.vue` |
| Properties | `resources/js/components/NetWorth/Property/` |
| Investments | `resources/js/components/NetWorth/InvestmentList.vue` |
| Savings | `resources/js/components/UserProfile/BankAccounts.vue` |
| Estate | `resources/js/components/Estate/IHTPlanning.vue` |
| Protection | `resources/js/views/Protection.vue` |
| Sidebar | `resources/js/components/SideMenu.vue` |

### Store Modules

| Store | What to Check |
|-------|--------------|
| `spousePermission.js` | `hasSpouse` getter — DONE (divorced excluded, widowed kept) |
| `netWorth.js` | Combined vs individual net worth |
| `estate.js` | Second death planning, combined estate |
| `retirement.js` | Spouse pension data |
| `protection.js` | Family coverage calculations |
| `investment.js` | Joint account visibility |
| `savings.js` | Joint account visibility |

## Implementation Approach

### Phase 1: Audit (Read Only)
- Read every file listed above
- Map every place where `spouse_id`, `joint_owner_id`, `hasSpouse`, `isMarried`, `forUserOrJoint` is used
- Document current behaviour for each module
- Identify which are already correct vs which need changes

### Phase 2: Backend — Spouse State Service
- Create or extend a service that encapsulates spouse lifecycle state
- Single source of truth: "can this user see spouse data?"
- Rules: married = yes, divorced = no, widowed = yes
- All modules query this instead of checking marital_status independently

### Phase 3: Frontend — Module Updates
- Update each module view to respect the spouse state service
- On marital status change, dispatch a global refresh of affected stores
- Ensure Vue reactivity picks up the changes without page reload

### Phase 4: Data Integrity
- On divorce: What happens to joint assets? Options:
  - Keep as joint (ownership unchanged, just hide spouse view)
  - Convert to individual (remove joint_owner_id)
  - Prompt user to decide per asset
- On widowed: Transfer deceased spouse's individual assets to estate?

### Phase 5: Testing
- Test each transition (single→married, married→divorced, married→widowed)
- Verify each module shows/hides correctly
- Verify net worth recalculates
- Verify estate planning recalculates
- Verify Fyn AI context reflects current state

## Open Questions (for next session)

1. **On divorce — what happens to joint assets?** Keep as joint but hide spouse, or prompt user to reassign ownership?
2. **On widowed — should there be a "settle estate" flow?** Transfer spouse's assets to user, recalculate IHT, etc.
3. **Should the backend enforce visibility?** Or is frontend-only gating sufficient? (Backend is safer but more work)
4. **Notifications?** Should the spouse receive any notification when they're removed?

## Priority Order

1. Net Worth (most visible, affects dashboard)
2. Estate Planning (second death calculations)
3. Properties (joint ownership)
4. Investments & Savings (joint accounts)
5. Protection (family coverage)
6. Retirement (spouse pension)
7. Fyn AI (context accuracy)
8. Goals & Life Events (shared items)
