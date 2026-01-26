# Code Review Improvement Tasks

## Date: 26 January 2026

---

## HIGH Priority Tasks

### [ ] Task 1: Add unit tests for financial calculation services
**Effort:** 8+ hours | **Status:** Deferred (requires dedicated testing sprint)

Test coverage is significantly below industry standards. Only 99 test files for 650+ source files.

**Focus areas:**
- Estate/IHTCalculationService
- Investment tax calculations
- Retirement projection services

---

### [ ] Task 2: Standardize error handling across all controllers
**Effort:** 2-3 hours | **Status:** Pending

Ensure all controllers use `SanitizedErrorResponse` trait consistently.

**Files to update:**
- `app/Http/Controllers/Api/ProtectionController.php`
- Any controller using inline `return response()->json()` for errors

---

### [ ] Task 3: Refactor large controller methods
**Effort:** 2-3 hours | **Status:** Pending

**Methods to refactor:**
- `PropertyController::store()` - 125+ lines
- `AuthController::verifyCode()` - 100+ lines

---

### [x] Task 4: Replace User::find() with findOrFail()
**Effort:** 1 hour | **Status:** Completed

**Result:** Reviewed and found existing code already handles null cases properly with explicit 404 responses (AdminController) or generic errors for security (MFAController). No changes needed.

---

## MEDIUM Priority Tasks

### [x] Task 5: Create query scope for joint owner pattern
**Effort:** 1 hour | **Status:** Completed

Created `HasJointOwnership` trait with `scopeForUserOrJoint()` method.

**Files created:**
- `app/Traits/HasJointOwnership.php`

**Models updated:**
- Property
- SavingsAccount
- Chattel
- BusinessInterest
- Mortgage
- InvestmentAccount
- Goal
- Estate/Liability

---

### [x] Task 6: Create Form Request classes for income protection
**Effort:** 30 minutes | **Status:** Completed

**Files created:**
- `app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php`
- `app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php`

**Files updated:**
- `app/Http/Controllers/Api/ProtectionController.php`

---

### [x] Task 7: Add validation to GiftForm.vue
**Effort:** 30 minutes | **Status:** Completed

**File updated:** `resources/js/components/Estate/GiftForm.vue`

Added validation for: gift_date, gift_value, gift_type, recipient

---

### [x] Task 8: Add retry logic to API service
**Effort:** 1 hour | **Status:** Completed

**File updated:** `resources/js/services/api.js`

Added exponential backoff with jitter for 5xx errors, network failures, and 429 rate limiting. Only retries idempotent requests (GET, PUT, DELETE).

---

### [ ] Task 9: Add prop type validation to Vue components
**Effort:** Ongoing | **Status:** Deferred (long-term improvement)

Add shape validation to props using generic Object or Array types.

---

### [x] Task 10: Audit database indexes for performance
**Effort:** 1-2 hours | **Status:** Completed

**File created:** `database/migrations/2026_01_26_150000_add_joint_owner_indexes.php`

Adds indexes on `joint_owner_id` columns for:
- properties
- savings_accounts
- investment_accounts
- mortgages
- chattels
- business_interests
- liabilities
- goals (composite index with status)

---

### [x] Task 11: Standardize cache tag usage across Agents
**Effort:** 1 hour | **Status:** Completed

All agents now consistently use cache tags for proper cache invalidation.

**Files updated:**
- `app/Agents/SavingsAgent.php` - Added tags ['savings', 'user_'.$userId]
- `app/Agents/InvestmentAgent.php` - Added tags ['investment', 'user_'.$userId]
- `app/Agents/RetirementAgent.php` - Added tags ['retirement', 'user_'.$userId]
- EstateAgent and ProtectionAgent already had tags

---

### [x] Task 12: Implement structured logging format
**Effort:** 2 hours | **Status:** Completed

**File created:** `app/Traits/StructuredLogging.php`

Provides:
- `logInfo()`, `logWarning()`, `logError()`, `logDebug()`
- `logAuth()` - for authentication events
- `logApiRequest()` - for API calls
- `logModelOperation()` - for CRUD operations
- `logCalculation()` - for financial calculations
- Automatic context with class, timestamp, user_id, request_id

---

## Progress Summary

| Priority | Total | Completed | Remaining |
|----------|-------|-----------|-----------|
| HIGH | 4 | 1 | 3 |
| MEDIUM | 8 | 7 | 1 |
| **Total** | **12** | **8** | **4** |

**Completed Tasks:** 4, 5, 6, 7, 8, 10, 11, 12
**Deferred Tasks:** 1 (testing sprint), 9 (long-term)
**Remaining Tasks:** 2, 3

**Files Created:**
- `app/Traits/HasJointOwnership.php`
- `app/Traits/StructuredLogging.php`
- `app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php`
- `app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php`
- `database/migrations/2026_01_26_150000_add_joint_owner_indexes.php`

**Files Modified:**
- 8 Models (added HasJointOwnership trait)
- 4 Agents (added cache tags)
- `app/Http/Controllers/Api/ProtectionController.php`
- `resources/js/services/api.js`
- `resources/js/components/Estate/GiftForm.vue`
