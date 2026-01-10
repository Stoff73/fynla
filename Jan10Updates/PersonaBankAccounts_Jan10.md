# Persona Bank Accounts - January 10, 2026

This document summarizes the bank account additions made to preview personas.

---

## Overview

Added current accounts and business accounts to all four preview personas to provide more realistic financial data.

---

## Changes by Persona

### Carters (young_family)

| Account | Provider | Type | Balance | Ownership |
|---------|----------|------|---------|-----------|
| James's Current Account | Lloyds Bank | current_account | £3,250 | Individual |
| Emily's Current Account | Nationwide | current_account | £2,180 | Individual (spouse) |
| Joint Savings Account | Marcus | instant_access | £8,500 | Joint |

### Mitchells (peak_earners)

| Account | Provider | Type | Balance | Ownership |
|---------|----------|------|---------|-----------|
| David's Current Account | HSBC | current_account | £8,450 | Individual |
| Sarah's Current Account | Barclays | current_account | £6,280 | Individual (spouse) |
| Joint Current Account | Nationwide | current_account | £4,500 | Joint |

### Thompson (widow)

| Account | Provider | Type | Balance | Ownership |
|---------|----------|------|---------|-----------|
| Margaret's Current Account | Lloyds Bank | current_account | £4,850 | Individual |

### Chen (entrepreneur)

| Account | Provider | Type | Balance | Ownership |
|---------|----------|------|---------|-----------|
| Alex's Current Account | Starling Bank | current_account | £5,680 | Individual |
| Chen Tech Consulting - Business Current | Tide | business_current | £48,500 | Individual |
| Chen Tech Consulting - Business Reserve | Tide | business_savings | £75,000 | Individual |

---

## Files Modified

| File | Changes |
|------|---------|
| `resources/js/data/personas/young_family.json` | Added 2 current accounts, renamed Emergency Fund to Joint Savings Account |
| `resources/js/data/personas/peak_earners.json` | Added 3 current accounts (2 individual + 1 joint) |
| `resources/js/data/personas/widow.json` | Added 1 current account |
| `resources/js/data/personas/entrepreneur.json` | Added 1 personal current account + 2 business accounts |

---

## Production Deployment

To apply these changes on the production server:

### Step 1: Deploy Updated Files

Ensure the updated persona JSON files are deployed:
- `resources/js/data/personas/young_family.json`
- `resources/js/data/personas/peak_earners.json`
- `resources/js/data/personas/widow.json`
- `resources/js/data/personas/entrepreneur.json`

### Step 2: Delete Existing Preview Users

Run this command to delete existing preview users and their related data:

```bash
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;

\$userIds = \App\Models\User::where('is_preview_user', true)->pluck('id')->toArray();

if (empty(\$userIds)) {
    echo 'No preview users found.';
    exit;
}

DB::table('savings_accounts')->whereIn('user_id', \$userIds)->delete();
DB::table('holdings')->whereIn('holdable_id', DB::table('investment_accounts')->whereIn('user_id', \$userIds)->pluck('id'))->where('holdable_type', 'App\\\Models\\\Investment\\\InvestmentAccount')->delete();
DB::table('investment_accounts')->whereIn('user_id', \$userIds)->delete();
DB::table('holdings')->whereIn('holdable_id', DB::table('dc_pensions')->whereIn('user_id', \$userIds)->pluck('id'))->where('holdable_type', 'App\\\Models\\\DCPension')->delete();
DB::table('dc_pensions')->whereIn('user_id', \$userIds)->delete();
DB::table('db_pensions')->whereIn('user_id', \$userIds)->delete();
DB::table('state_pensions')->whereIn('user_id', \$userIds)->delete();
DB::table('properties')->whereIn('user_id', \$userIds)->delete();
DB::table('mortgages')->whereIn('user_id', \$userIds)->delete();
DB::table('life_insurance_policies')->whereIn('user_id', \$userIds)->delete();
DB::table('critical_illness_policies')->whereIn('user_id', \$userIds)->delete();
DB::table('income_protection_policies')->whereIn('user_id', \$userIds)->delete();
DB::table('family_members')->whereIn('user_id', \$userIds)->delete();
DB::table('risk_profiles')->whereIn('user_id', \$userIds)->delete();
DB::table('retirement_profiles')->whereIn('user_id', \$userIds)->delete();
DB::table('bequests')->whereIn('user_id', \$userIds)->delete();
DB::table('wills')->whereIn('user_id', \$userIds)->delete();
DB::table('trusts')->whereIn('user_id', \$userIds)->delete();
DB::table('business_interests')->whereIn('user_id', \$userIds)->delete();
DB::table('chattels')->whereIn('user_id', \$userIds)->delete();
DB::table('liabilities')->whereIn('user_id', \$userIds)->delete();
DB::table('users')->whereIn('id', \$userIds)->delete();

echo count(\$userIds) . ' preview users deleted.';
"
```

### Step 3: Reseed Preview Users

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## New Account Types Introduced

Two new account types were used:
- `current_account` - Standard current/checking account
- `business_current` - Business current account
- `business_savings` - Business savings/reserve account

These may need to be added to any frontend dropdowns or validation rules that enumerate account types.
