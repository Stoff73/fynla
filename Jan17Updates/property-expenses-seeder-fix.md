# Property Expenses Seeder Fix

**Date:** January 17, 2026
**Issue:** Monthly property expenses were showing as £0 for all preview personas

## Problem

The PreviewUserSeeder was not mapping the monthly expense fields from the persona JSON files to the database. Properties had expense data in the JSON but it wasn't being stored.

## Root Cause

The `createProperties()` method in PreviewUserSeeder only mapped basic property fields, not the monthly expense fields:
- monthly_council_tax
- monthly_gas
- monthly_electricity
- monthly_water
- monthly_building_insurance
- monthly_contents_insurance
- monthly_service_charge
- monthly_ground_rent
- monthly_maintenance_reserve
- monthly_letting_agent_fee
- monthly_landlord_insurance
- other_monthly_costs

## Solution

### 1. Updated PreviewUserSeeder

**File:** `database/seeders/PreviewUserSeeder.php`

Added all expense fields to the Property::create() call:

```php
$property = Property::create([
    // ... existing fields ...

    // Monthly expenses
    'monthly_council_tax' => $prop['monthly_council_tax'] ?? null,
    'monthly_gas' => $prop['monthly_gas'] ?? null,
    'monthly_electricity' => $prop['monthly_electricity'] ?? null,
    'monthly_water' => $prop['monthly_water'] ?? null,
    'monthly_building_insurance' => $prop['monthly_building_insurance'] ?? null,
    'monthly_contents_insurance' => $prop['monthly_contents_insurance'] ?? null,
    'monthly_service_charge' => $prop['monthly_service_charge'] ?? null,
    'monthly_ground_rent' => $prop['monthly_ground_rent'] ?? null,
    'monthly_maintenance_reserve' => $prop['monthly_maintenance_reserve'] ?? null,
    'monthly_letting_agent_fee' => $prop['monthly_letting_agent_fee'] ?? null,
    'monthly_landlord_insurance' => $prop['monthly_landlord_insurance'] ?? null,
    'other_monthly_costs' => $prop['other_monthly_costs'] ?? null,
]);
```

### 2. Updated All Persona JSON Files

Added realistic expense data to all properties in persona files:

**Files updated:**
- `resources/js/data/personas/peak_earners.json`
- `resources/js/data/personas/young_family.json`
- `resources/js/data/personas/widow.json`
- `resources/js/data/personas/entrepreneur.json`
- `resources/js/data/personas/retired_couple.json`

Example expense data for main residence:
```json
{
  "monthly_council_tax": 245,
  "monthly_gas": 95,
  "monthly_electricity": 85,
  "monthly_water": 45,
  "monthly_building_insurance": 35,
  "monthly_contents_insurance": 25
}
```

Example expense data for BTL property:
```json
{
  "monthly_building_insurance": 28,
  "monthly_service_charge": 195,
  "monthly_maintenance_reserve": 85,
  "monthly_letting_agent_fee": 162,
  "monthly_landlord_insurance": 38,
  "other_monthly_costs": 50
}
```

## Files Changed

- `database/seeders/PreviewUserSeeder.php`
- `resources/js/data/personas/peak_earners.json`
- `resources/js/data/personas/young_family.json`
- `resources/js/data/personas/widow.json`
- `resources/js/data/personas/entrepreneur.json`
- `resources/js/data/personas/retired_couple.json`

## Testing

After updating:
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

Then verify:
1. Login as any preview persona
2. Navigate to Net Worth > Property
3. Click on a property and go to Financials tab
4. Monthly expenses should now show actual values instead of £0
