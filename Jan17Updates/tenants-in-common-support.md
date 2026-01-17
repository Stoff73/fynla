# Tenants in Common Property Support

**Date:** January 17, 2026
**Feature:** Added support for Tenants in Common (TIC) ownership for properties and mortgages

## Overview

Extended the property and mortgage ownership types to include "tenants_in_common" as a valid option, allowing for properties owned with non-linked joint owners (e.g., business partners, friends).

## Changes Made

### 1. Database Migration

Created migration to add `tenants_in_common` to mortgages ownership_type enum:

**File:** `database/migrations/2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type.php`

```php
public function up(): void
{
    DB::statement("ALTER TABLE mortgages MODIFY COLUMN ownership_type
        ENUM('individual', 'joint', 'tenants_in_common', 'trust')
        NOT NULL DEFAULT 'individual'");
}
```

### 2. PreviewUserSeeder Updates

**File:** `database/seeders/PreviewUserSeeder.php`

Updated `createProperties()` method to handle:
- `tenants_in_common` ownership type
- `joint_owner_name` field for non-linked owners

```php
$ownershipType = $prop['ownership_type'] ?? 'individual';
$isSharedOwnership = in_array($ownershipType, ['joint', 'tenants_in_common']);

$jointOwnerId = null;
$jointOwnerName = null;

if ($isSharedOwnership) {
    if (!empty($prop['joint_owner_name'])) {
        // TIC with non-linked owner (e.g., business partner)
        $jointOwnerName = $prop['joint_owner_name'];
    } elseif ($spouse) {
        // Joint with linked spouse
        $jointOwnerId = $spouse->id;
    }
}
```

Updated `createMortgages()` method with the same pattern.

### 3. Sample Data - Peak Earners Persona

**File:** `resources/js/data/personas/peak_earners.json`

Added new Tenants in Common property for the Mitchells:

```json
{
  "id": 3,
  "property_name": "Manchester Investment Property",
  "address": "Unit 12, Victoria Mill, Ancoats, Manchester, M4 6AG",
  "property_type": "buy_to_let",
  "current_value": 295000,
  "ownership_type": "tenants_in_common",
  "ownership_percentage": 40,
  "joint_owner_name": "Mike Jones",
  "monthly_rental_income": 1350,
  "monthly_building_insurance": 28,
  "monthly_service_charge": 195,
  "monthly_maintenance_reserve": 85,
  "other_monthly_costs": 120,
  "tenant_name": "Ms. Rachel Green"
}
```

With corresponding mortgage:

```json
{
  "id": 3,
  "property_id": 3,
  "lender": "Aldermore Bank",
  "outstanding_balance": 177000,
  "original_amount": 200000,
  "interest_rate": 5.89,
  "mortgage_type": "interest_only",
  "ownership_type": "tenants_in_common",
  "ownership_percentage": 40,
  "joint_owner_name": "Mike Jones",
  "term_end_date": "2035-09-01"
}
```

## Key Differences: Joint vs Tenants in Common

| Aspect | Joint Ownership | Tenants in Common |
|--------|-----------------|-------------------|
| Owner Link | Uses `joint_owner_id` (linked user) | Uses `joint_owner_name` (text field) |
| Typical Use | Married couples | Business partners, friends |
| Percentage | Usually 50/50 | Can be any split (e.g., 40/60) |
| Survivorship | Automatic right of survivorship | No automatic survivorship |

## Files Changed

- `database/migrations/2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type.php`
- `database/seeders/PreviewUserSeeder.php`
- `resources/js/data/personas/peak_earners.json`

## Testing

After migration:
```bash
php artisan migrate
php artisan db:seed --class=PreviewUserSeeder --force
```

Login as peak_earners persona and verify:
- Manchester Investment Property appears with 40% ownership
- Joint owner shown as "Mike Jones" (not linked user)
- Mortgage shows same TIC ownership details
