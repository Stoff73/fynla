# January 1, 2026 Updates

## Summary

This folder documents changes made on January 1, 2026 for Fynla v0.4.5.

## Changes

### 1. Chattels Module Implementation
**File:** `Chattels_Implementation_Plan.md`

Full implementation of the chattels section in the Net Worth module:

**Features:**
- Track vehicles, art, antiques, jewelry, collectibles, and other valuable personal assets
- UK-compliant CGT calculator with:
  - £6,000 threshold exemption (no CGT if disposal proceeds ≤ £6,000)
  - Marginal relief (5/3 rule) for proceeds £6,000-£15,000
  - Wasting asset exemption for vehicles (predictable life ≤50 years)
  - 10%/20% non-residential CGT rates based on income
- Joint ownership support with spouse
- Full CRUD operations via API

**New Files (8):**
| File | Purpose |
|------|---------|
| `app/Services/Chattel/ChattelCGTService.php` | CGT calculation with marginal relief |
| `app/Http/Controllers/Api/ChattelController.php` | CRUD + CGT endpoint |
| `app/Http/Requests/Chattel/StoreChattelRequest.php` | Create validation |
| `app/Http/Requests/Chattel/UpdateChattelRequest.php` | Update validation |
| `resources/js/services/chattelService.js` | Frontend API wrapper |
| `resources/js/store/modules/chattels.js` | Vuex store module |
| `resources/js/components/NetWorth/ChattelFormModal.vue` | Add/edit form modal |
| `resources/js/components/NetWorth/ChattelDetailInline.vue` | Detail view with CGT calc |

**Updated Files (5):**
| File | Changes |
|------|---------|
| `app/Models/Chattel.php` | Added Trust model import, joint_owner_id to fillable |
| `routes/api.php` | Added chattel routes (6 endpoints) |
| `resources/js/store/index.js` | Registered chattels module |
| `resources/js/components/NetWorth/ChattelsList.vue` | Removed Coming Soon, full CRUD functionality |
| `resources/js/components/NetWorth/ChattelCard.vue` | Fixed field names, added click handler |

**API Endpoints:**
```
GET     /api/chattels                    - List all chattels
POST    /api/chattels                    - Create chattel
GET     /api/chattels/{id}               - Get single chattel
PUT     /api/chattels/{id}               - Update chattel
DELETE  /api/chattels/{id}               - Delete chattel
POST    /api/chattels/{id}/calculate-cgt - Calculate CGT for disposal
```

**Preview Data:**
Mitchell persona (peak_earners) updated with 6 test chattels:
- 1967 Jaguar E-Type (vehicle, CGT exempt)
- BMW X5 xDrive40i (joint vehicle, CGT exempt)
- Contemporary Art Collection (joint art)
- Sarah's Engagement Ring (jewelry, spouse)
- Georgian Writing Desk (joint antique)
- First Edition Book Collection (collectible, below threshold)

---

### 2. Version Bump to v0.4.5

**Files Updated (8):**
| File | Change |
|------|--------|
| `CLAUDE.md` | Version v0.4.4 → v0.4.5 |
| `README.md` | Version v0.4.4 → v0.4.5, deployment date to January 1, 2026 |
| `resources/js/components/Footer.vue` | Version link to v0.4.5 |
| `resources/js/layouts/PublicLayout.vue` | Version link to v0.4.5 |
| `resources/js/views/Version.vue` | v0.4.5 release notes with Chattels features |
| `deploy/csjones-fynla/.htaccess` | Version in header comment |
| `deploy/fynla-org/.htaccess` | Version in header comment |
| `DEPLOYMENT_FYNLA_ORG.md` | Version in htaccess example |

---

### 3. UI Cleanup

**Summary Bar Removal:**
- Removed unstyled summary bar from ChattelsList.vue (Total Items, Total Value, CGT Exempt)
- Cleaned up unused imports and computed properties

---

### 4. Info Guide Navigation Fix

**Bug:** The floating help button was not updating when navigating between modules - it always showed dashboard requirements.

**Root Cause:** The router `afterEach` hook wasn't reliably triggering module detection.

**Fix:**
- Added `$route.path` watcher directly in `InfoGuidePanel.vue` with `immediate: true`
- Sort module prefixes by length before matching (longer paths checked first)
- Fixed race condition by setting `currentModule` before making API call
- Added validation to only set requirements if module hasn't changed during fetch

**Files Modified:**
- `resources/js/components/Shared/InfoGuidePanel.vue` - Added route watcher
- `resources/js/store/modules/infoGuide.js` - Fixed fetchRequirements action

---

### 5. Granular Info Guide Modules

**Enhancement:** Each screen now shows its own specific data requirements, not just generic module info.

**New Modules Added:**
| Module | Route | Description |
|--------|-------|-------------|
| `trusts` | `/trusts` | Trust-specific requirements (beneficiaries, trust details) |
| `properties` | `/net-worth/properties` | Property portfolio tracking |
| `liabilities` | `/net-worth/liabilities` | Debt and loan management |
| `business_interests` | `/net-worth/business-interests` | Business ownership tracking |
| `chattels` | `/net-worth/chattels` | Valuable items and CGT |
| `profile` | `/profile` | Comprehensive personal profile |

**Files Modified:**
- `app/Services/UserProfile/ModuleDataRequirementsService.php` - Added 6 new module definitions
- `resources/js/components/Shared/InfoGuidePanel.vue` - Updated route mappings

---

## Git History

```
0368c98 docs: Update Jan1Updates with granular Info Guide modules
0bdac3b feat: Add granular Info Guide modules for each screen
1ce3970 docs: Update Jan1Updates with Info Guide navigation fix
c6a14ab fix: Info Guide now updates when navigating between modules
3e60283 docs: Add Jan1Updates README summarizing v0.4.5 changes
a96d328 chore: Bump version to v0.4.5
f458663 fix: Remove summary bar from chattels list
9a26976 feat: Implement chattels module with CGT calculator
```

---

## Deployment Status

**Version:** v0.4.5
**Status:** Ready for deployment
**Branch:** main (pushed to origin)

To deploy, follow the standard deployment process in `DEPLOYMENT_FYNLA_ORG.md` or use the build scripts:
```bash
./deploy/fynla-org/build.sh        # For fynla.org
./deploy/csjones-fynla/build.sh    # For csjones.co/fynla
```
