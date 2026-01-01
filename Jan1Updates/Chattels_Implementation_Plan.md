# Chattels Feature Implementation Plan

## Status: COMPLETE

**Completed**: 1 January 2026

---

## Overview
Implement the chattels section in the Net Worth module with CRUD operations, joint ownership support, CGT calculation (including £6,000 threshold, marginal relief, and wasting asset exemption), and estate integration.

## Requirements Confirmed
- CGT calculator with £6,000 threshold, marginal relief (5/3 rule), wasting asset exemption
- Joint ownership support via joint_owner_id and ownership_percentage
- All chattel types: vehicles, art, antiques, jewelry, collectibles, other

## CGT Rules (from gov.uk)
| Rule | Implementation |
|------|----------------|
| £6,000 threshold | No CGT if disposal proceeds ≤ £6,000 |
| Marginal relief | For proceeds £6,000-£15,000: max gain = (proceeds - £6,000) × 5/3 |
| Wasting assets | Vehicles (life ≤50 years) are CGT-EXEMPT |
| Rate | 10% basic rate / 20% higher rate (not residential rates) |

## Existing Infrastructure (No Changes Needed)
- Database: `chattels` table exists with `joint_owner_id` column ✓
- Model: `app/Models/Chattel.php` exists ✓
- Estate integration: `EstateAssetAggregatorService.php` lines 138-155 ✓
- Router: `/net-worth/chattels` route configured ✓

---

## Implementation Completed

### Phase 1: Backend - Model Update ✓
**File**: `app/Models/Chattel.php`
- Added `joint_owner_id` to `$fillable` array
- Added `jointOwner()` BelongsTo relationship

### Phase 2: Backend - Services ✓
**New File**: `app/Services/Chattel/ChattelCGTService.php`
- `calculateCGT()` - Full CGT calculation with all rules
- `isWastingAsset()` - Checks if vehicle (CGT exempt)
- `calculateLoss()` - Loss calculation with £6,000 floor
- `determineCGTRate()` - 10%/20% based on income
- `wouldBeExempt()` - Quick exemption check

### Phase 3: Backend - Validation ✓
**New File**: `app/Http/Requests/Chattel/StoreChattelRequest.php`
- chattel_type: required, in:[vehicle, art, antique, jewelry, collectible, other]
- name: required, string, max:255
- current_value: required, numeric, min:0
- ownership_type: nullable, in:[individual, joint, trust]
- ownership_percentage: nullable, numeric, 0-100
- joint_owner_id: nullable, exists:users,id
- Vehicle fields (make, model, year, registration_number): nullable

**New File**: `app/Http/Requests/Chattel/UpdateChattelRequest.php`
- Same rules with 'sometimes' instead of 'required'

### Phase 4: Backend - Controller ✓
**New File**: `app/Http/Controllers/Api/ChattelController.php`

Pattern: Follows `PropertyController.php` single-record architecture
- Uses `CalculatesOwnershipShare` trait
- Query: `where('user_id', $id)->orWhere('joint_owner_id', $id)`
- Only primary owner can update/delete

Methods:
| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET /api/chattels | List all chattels for user |
| `store` | POST /api/chattels | Create chattel |
| `show` | GET /api/chattels/{id} | Get single chattel |
| `update` | PUT /api/chattels/{id} | Update chattel |
| `destroy` | DELETE /api/chattels/{id} | Delete chattel |
| `calculateCGT` | POST /api/chattels/{id}/calculate-cgt | Calculate CGT for disposal |

### Phase 5: Backend - Routes ✓
**File**: `routes/api.php`
```php
Route::middleware('auth:sanctum')->prefix('chattels')->group(function () {
    Route::get('/', [ChattelController::class, 'index']);
    Route::post('/', [ChattelController::class, 'store']);
    Route::get('/{id}', [ChattelController::class, 'show']);
    Route::put('/{id}', [ChattelController::class, 'update']);
    Route::delete('/{id}', [ChattelController::class, 'destroy']);
    Route::post('/{id}/calculate-cgt', [ChattelController::class, 'calculateCGT']);
});
```

### Phase 6: Frontend - API Service ✓
**New File**: `resources/js/services/chattelService.js`
- getChattels(), getChattel(id), createChattel(data), updateChattel(id, data), deleteChattel(id)
- calculateCGT(id, { disposal_price, disposal_costs })

### Phase 7: Frontend - Vuex Store ✓
**New File**: `resources/js/store/modules/chattels.js`
- State: chattels, selectedChattel, cgtCalculation, loading, error
- Getters: chattelsByType, totalChattelValue, vehicleChattels, wastingAssets, taxableChattels
- Actions: fetchChattels, createChattel, updateChattel, deleteChattel, calculateCGT

**Updated**: `resources/js/store/index.js` - Registered chattels module

### Phase 8: Frontend - Components ✓

**Updated**: `resources/js/components/NetWorth/ChattelsList.vue`
- Removed "Coming Soon" banner and opacity wrapper
- Connected to Vuex store
- Filter by type dropdown
- Summary bar with totals and CGT exempt count
- Integrated ChattelFormModal and ChattelDetailInline

**Updated**: `resources/js/components/NetWorth/ChattelCard.vue`
- Fixed field names: `chattel.chattel_name` → `chattel.name`
- Added click handler for navigation
- Shows "CGT Exempt" badge for wasting assets
- Displays user_share for joint ownership

**New File**: `resources/js/components/NetWorth/ChattelFormModal.vue`
- Uses @save not @submit (avoids double submission bug)
- Type selection grid (Vehicle, Art, Antique, Jewelry, Collectible, Other)
- Vehicle details section (conditional)
- Ownership section with joint owner support
- Valuation section with current value and purchase info

**New File**: `resources/js/components/NetWorth/ChattelDetailInline.vue`
- Three tabs: Overview, CGT Calculator, Notes
- CGT Calculator with disposal price/costs inputs
- Full breakdown: exemption status, marginal relief, taxable gain, liability
- Wasting asset exemption notice for vehicles
- Edit/Delete buttons (primary owner only)

---

## Files Summary

### New Files Created (8)
| Path | Purpose |
|------|---------|
| `app/Services/Chattel/ChattelCGTService.php` | CGT calculation with marginal relief |
| `app/Http/Controllers/Api/ChattelController.php` | CRUD + CGT endpoint |
| `app/Http/Requests/Chattel/StoreChattelRequest.php` | Create validation |
| `app/Http/Requests/Chattel/UpdateChattelRequest.php` | Update validation |
| `resources/js/services/chattelService.js` | Frontend API wrapper |
| `resources/js/store/modules/chattels.js` | Vuex store module |
| `resources/js/components/NetWorth/ChattelFormModal.vue` | Add/edit form modal |
| `resources/js/components/NetWorth/ChattelDetailInline.vue` | Detail view with CGT calc |

### Files Updated (5)
| Path | Changes |
|------|---------|
| `app/Models/Chattel.php` | Added joint_owner_id to fillable, added jointOwner relationship |
| `routes/api.php` | Added chattel routes (6 endpoints) |
| `resources/js/store/index.js` | Registered chattels module |
| `resources/js/components/NetWorth/ChattelsList.vue` | Removed Coming Soon, full CRUD functionality |
| `resources/js/components/NetWorth/ChattelCard.vue` | Fixed field names, added click handler |

---

## Verification

### PHP Syntax Check ✓
All PHP files pass syntax validation:
- ChattelCGTService.php
- ChattelController.php
- StoreChattelRequest.php
- UpdateChattelRequest.php

### API Routes Registered ✓
```
GET|HEAD  api/chattels ......................... Api\ChattelController@index
POST      api/chattels ......................... Api\ChattelController@store
GET|HEAD  api/chattels/{id} .................... Api\ChattelController@show
PUT       api/chattels/{id} .................... Api\ChattelController@update
DELETE    api/chattels/{id} .................... Api\ChattelController@destroy
POST      api/chattels/{id}/calculate-cgt ...... Api\ChattelController@calculateCGT
```

### Frontend Build ✓
- `npm run build` completes successfully
- ChattelsList bundle created: `ChattelsList-B27Jf3IF.js` (39.64 kB)

### CGT Calculation Logic ✓
| Test | Result |
|------|--------|
| Vehicle (wasting asset) | EXEMPT |
| Disposal at £5,000 (below threshold) | EXEMPT |
| Marginal relief for £10,000 disposal | Applied correctly (£6,666.67 cap) |
| Disposal at £20,000 (above marginal range) | No marginal relief needed |
| CGT rates | 10% basic / 20% higher |

### API Endpoint Test ✓
- GET /api/chattels returns empty array for new users
- POST /api/chattels creates chattel successfully (preview mode: session-only)

---

## Additional Updates

### Chattel Model Fix
- Added `use App\Models\Estate\Trust;` import to fix "Class not found" error

### Preview User Seeder Updated
- Added `use App\Models\Chattel;` import
- Added `createChattels()` method to seed chattel data from persona JSON
- Supports owner detection (spouse flag, name matching)
- Supports joint ownership with spouse

### Mitchell Persona Data (peak_earners.json)
Updated chattels array with 6 test items:

| Chattel | Type | Value | Ownership | CGT Status |
|---------|------|-------|-----------|------------|
| 1967 Jaguar E-Type | Vehicle | £85,000 | David 100% | Exempt (wasting) |
| BMW X5 xDrive40i | Vehicle | £42,000 | Joint 50% | Exempt (wasting) |
| Contemporary Art Collection | Art | £35,000 | Joint 50% | Taxable |
| Sarah's Engagement Ring | Jewelry | £18,000 | Sarah 100% | Taxable |
| Georgian Writing Desk | Antique | £8,500 | Joint 50% | Taxable |
| First Edition Book Collection | Collectible | £4,500 | David 100% | Below threshold |

### Seeder Command
```bash
# Delete and reseed peak_earners persona with chattels
php artisan tinker --execute="App\Models\User::where('email', 'like', '%peak_earners%')->forceDelete();"
php artisan db:seed --class=PreviewUserSeeder --force
```
