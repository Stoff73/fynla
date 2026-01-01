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

## Git History

```
a96d328 chore: Bump version to v0.4.5
f458663 fix: Remove summary bar from chattels list
bbe9f7d docs: Update Dec31Updates with deployment steps for next session
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
