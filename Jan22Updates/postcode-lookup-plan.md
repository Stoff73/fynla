# Postcode Lookup Implementation Plan

## Summary
Add UK address lookup using GetAddress.io API to all address forms in Fynla. When a user enters a postcode and clicks "Find Address", a dropdown shows all addresses at that postcode. Selecting an address auto-fills all fields (address_line_1, address_line_2, city, county, postcode).

## API: GetAddress.io
- **Requires API key** (stored in `.env`)
- **Free tier**: 20 lookups/day + 30 extra monthly (sufficient for dev/testing)
- **Paid**: From £20/month for 1,000 lookups/day
- **Endpoint**: `GET https://api.getaddress.io/autocomplete/{postcode}?api-key={key}`
- **Returns**: List of address suggestions with full address strings

## Backend Setup

### 1. Environment Configuration
Add to `.env`:
```
GETADDRESS_API_KEY=your_api_key_here
```

Add to `config/services.php`:
```php
'getaddress' => [
    'api_key' => env('GETADDRESS_API_KEY'),
],
```

### 2. Laravel Proxy Route (to protect API key)
Create `routes/api.php` route:
```php
Route::get('/postcode-lookup/{postcode}', [PostcodeLookupController::class, 'lookup'])
    ->middleware('auth:sanctum');
```

### 3. `app/Http/Controllers/Api/PostcodeLookupController.php`
- Validates UK postcode format
- Calls GetAddress.io API with server-side API key
- Returns list of addresses or error
- Caches results briefly to reduce API calls

---

## Files Created

### Backend
| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/PostcodeLookupController.php` | Proxy controller for GetAddress.io API |

### Frontend
| File | Purpose |
|------|---------|
| `resources/js/services/postcodeService.js` | API wrapper with findAddresses(), isValidFormat(), normalise() |
| `resources/js/components/Shared/PostcodeLookup.vue` | Reusable component with postcode input, Find Address button, address dropdown |

---

## Files Modified

### Frontend Components
| File | Changes |
|------|---------|
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Added PostcodeLookup (UK only), handleAddressSelected method |
| `resources/js/components/UserProfile/PersonalInformation.vue` | Added PostcodeLookup (when editing), handleAddressSelected method |
| `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` | Added PostcodeLookup, handleAddressSelected method |
| `resources/js/components/Estate/AssetForm.vue` | Added PostcodeLookup for property assets |

### Backend Configuration
| File | Changes |
|------|---------|
| `config/services.php` | Added getaddress config section |
| `routes/api.php` | Added postcode-lookup route with auth:sanctum middleware |

---

## Implementation Sequence

1. **Backend**: Add `GETADDRESS_API_KEY` to `.env` and `config/services.php`
2. **Backend**: Create `PostcodeLookupController.php` with proxy endpoint
3. **Backend**: Add route in `routes/api.php`
4. **Frontend**: Create `postcodeService.js`
5. **Frontend**: Create `PostcodeLookup.vue` component
6. **Frontend**: Integrate into `PropertyForm.vue` (has country conditional)
7. **Frontend**: Integrate into `PersonalInformation.vue`
8. **Frontend**: Integrate into `PersonalInfoStep.vue`
9. **Frontend**: Integrate into `AssetForm.vue`
10. End-to-end testing across all forms

---

## Error Handling
- Invalid format: "Please enter a valid UK postcode (e.g., SW1A 1AA)"
- Not found: "Postcode not found. Please check and try again."
- API error: "Address lookup unavailable. Please enter address manually."
- **Non-blocking**: Lookup failure never prevents form submission
- **Manual fallback**: Address fields always remain editable

---

## Verification

1. **Dev server**: `./dev.sh`
2. **Test PropertyForm**:
   - Add property → enter UK postcode (e.g., "SW1A 1AA") → click "Find Address"
   - Verify dropdown shows list of addresses at that postcode
   - Select an address → verify ALL fields populate (address_line_1, address_line_2, city, county, postcode)
3. **Test non-UK**: Change country to non-UK → verify lookup component hidden
4. **Test PersonalInfo**: Profile → Edit → enter postcode → select address → verify all fields populate
5. **Test Onboarding**: Start onboarding flow → verify lookup works on Personal Info step
6. **Test Estate AssetForm**: Add estate asset (property type) → verify lookup populates full address string
7. **Test error cases**:
   - Invalid postcode format → error message shown
   - Valid format but no results → "No addresses found" message
   - API unavailable → graceful fallback to manual entry

---

## API Selection: Why GetAddress.io?

We evaluated three UK postcode/address lookup APIs:

### 1. postcodes.io (Not Selected)
- **Pros**: Completely free, no authentication required
- **Cons**: Only returns location data (city, county, region) - **does NOT return street addresses**
- **Why rejected**: A UK postcode covers 15-100 individual addresses. postcodes.io cannot tell you *which* address at a postcode is the user's.

### 2. Ideal Postcodes (Not Selected)
- **Pros**: Premium Royal Mail PAF data, industry standard, full address lookup
- **Cons**: Only 100 free lookups total (not ongoing), then pay-as-you-go (~£25/1000 lookups)
- **Why rejected**: The 100 lookup limit is quickly exhausted during development/testing.

### 3. GetAddress.io (Selected)
- **Pros**:
  - 20 free lookups/day ongoing (sufficient for dev/testing)
  - Full address lookup with street names
  - Returns list of all addresses at a postcode for user selection
  - Same Royal Mail PAF data quality as Ideal Postcodes
  - Affordable production pricing (from £20/month)
- **Cons**: Requires API key (but we proxy through Laravel to protect it)

**Decision**: GetAddress.io provides the best balance of free tier for development and full address lookup capability.

---

## Bug Fix: API Endpoint Change (22 Jan 2026)

**Issue**: GetAddress.io deprecated their `/find/{postcode}` endpoint.

**Root Cause**: All requests to `/find/{postcode}` now return 404 "Not Found" errors.

**Fix**: Updated to use `/autocomplete/{postcode}` endpoint which returns address suggestions in format:
```json
{
  "suggestions": [
    {"address": "1 Amherst Place, Sevenoaks, Kent, TN13 3BT", "id": "..."}
  ]
}
```

**Commit**: `f718a76` - fix: Use GetAddress.io autocomplete API for postcode lookup
