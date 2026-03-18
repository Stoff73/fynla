# Admin Panel Enhancement — March 17-18, 2026

**Status:** Merged to main (PR #135)
**Branch:** `feature/admin-enhancement` (deleted after merge)

---

## What Was Built

### 1. Decision Matrix Visualiser
Visual decision tree for all 6 modules showing how the recommendation engine works — from user data inputs through trigger conditions, decision logic, and outcomes.

- 6 module sub-tabs: Protection (28), Cash & Savings (41), Investments (21), Retirement (18), Estate Planning (8), Tax (5)
- 4-column horizontal tree: User Data > Trigger > Logic > Outcome
- Stats bar: total, enabled, disabled, critical/high, medium counts
- Click any node to edit via slide-in drawer (420px, all fields)
- Search, filter, collapse controls
- Priority badges: CRIT/HIGH/MED/LOW/OFF
- Disabled rows shown at 0.45 opacity with dashed arrows

### 2. EstateActionDefinition (New Module)
- Model, migration, seeder (8 definitions), evaluation service
- Completes the 6th module alongside Protection, Savings, Investment, Retirement, Tax
- Definitions: no_will, policy_not_in_trust, iht_exceeds_nrb, no_lpa, no_lpa_health, gifts_pet_window, trust_review_due, beneficiary_review

### 3. Generic ActionDefinitionController
- Single controller replacing 3 duplicated per-module controllers
- ALLOWED_MODULES whitelist with static array lookup (no string interpolation)
- Dynamic validation per module via StoreActionDefinitionRequest
- 7 API endpoints: list, show, create, update, delete, toggle, decision-matrix

### 4. Enhanced User Management
- P S I R E module status dots per user row
- Colour coded: complete (spring), partial (violet), empty (light-gray), skipped (eggshell)
- Expandable rows showing granular sub-area breakdown per module
- Onboarding progress card with completion status and journey states
- API: GET /api/admin/users/{id}/module-status

### 5. Database Backup Fixes
- Rate limit removed from GET /backup/list (was causing errors on page load)
- Write operations (create/restore/delete) still rate limited at 3/min
- Friendly error messages instead of raw "Too Many Attempts"

### 6. Admin Panel Tab Consolidation
- Replaced 3 per-module action tabs (Retirement, Investment, Protection) with single Decision Matrix tab
- Final tabs: Dashboard, User Management, Decision Matrix, Tax Settings, Database

---

## Files

| File | Purpose |
|------|---------|
| `decision-tree-mockup.html` | Visual mockup used as reference |
| `implementation-audit.md` | Full audit report with spec/mockup compliance checks |
| `deployAdmin.md` | Deployment guide with file list and SSH commands |
| `README.md` | This file |

---

## Key References

- **Spec:** `docs/superpowers/specs/2026-03-17-admin-advisor-design.md` (Feature 1)
- **Plan:** `docs/superpowers/plans/2026-03-17-admin-enhancement.md`
- **Design guide:** `fynlaDesignGuide.md` v1.2.0
