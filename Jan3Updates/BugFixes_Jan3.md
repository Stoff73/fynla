# Bug Fixes - January 3, 2026

This document summarizes the bugs found during end-to-end testing and their fixes.

## Bug #3: Risk Profile Not Persisting (CRITICAL)

### Symptoms
- User selects a risk level and time horizon on the Risk Profile page
- Clicks "Save Risk Profile" - button shows saving state
- Page redirects (if coming from investment/pension form)
- When returning to Risk Profile page, the selection is lost
- Attempting to add a pension or investment keeps redirecting back to Risk Profile page

### Root Cause
The `risk_profiles` table had several columns marked as `NOT NULL` without default values:
- `risk_tolerance` (enum)
- `capacity_for_loss_percent` (decimal)
- `time_horizon_years` (int)
- `knowledge_level` (enum)

The `RiskPreferenceService::setMainRiskLevel()` only sets `risk_level`, `risk_assessed_at`, and `is_self_assessed`, causing a MySQL error:
```
SQLSTATE[HY000]: General error: 1364 Field 'capacity_for_loss_percent' doesn't have a default value
```

### Fix
Created migration to make these columns nullable:

**File:** `database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php`

```php
public function up(): void
{
    DB::statement("ALTER TABLE risk_profiles MODIFY COLUMN risk_tolerance ENUM('cautious','balanced','adventurous') NULL");
    DB::statement('ALTER TABLE risk_profiles MODIFY COLUMN capacity_for_loss_percent DECIMAL(5,2) NULL');
    DB::statement('ALTER TABLE risk_profiles MODIFY COLUMN time_horizon_years INT NULL');
    DB::statement("ALTER TABLE risk_profiles MODIFY COLUMN knowledge_level ENUM('novice','intermediate','experienced') NULL");
}
```

### Deployment Steps
```bash
php artisan migrate --force
```

---

## Bug #4: Estate Planning Will Status Not Syncing (MEDIUM)

### Symptoms
- User saves will information in User Profile (Profile > Will tab)
- Data is correctly saved in the database
- Estate Planning module shows "Incomplete - No will recorded"
- The IHTPlanning.vue component displays incorrect will status

### Root Cause
The `IHTController::calculateIHT()` method returns an API response that includes:
- `success`
- `calculation`
- `assets_breakdown`
- `liabilities_breakdown`
- `iht_summary`

But it did NOT include `will_info`, which the frontend expects at `secondDeathData?.will_info?.has_will`.

The will information is stored in a separate `wills` table but was not being included in the IHT calculation response.

### Fix
Updated `IHTController.php` to include will information in the response:

**File:** `app/Http/Controllers/Api/Estate/IHTController.php`

Added import:
```php
use App\Models\Estate\Will;
```

Added will_info to response (before `return response()->json($response);`):
```php
// Add will information for estate planning status display
$will = Will::where('user_id', $user->id)->first();
$response['will_info'] = [
    'has_will' => $will?->has_will ?? false,
    'last_updated' => $will?->will_last_updated?->toIso8601String(),
    'executor_name' => $will?->executor_name,
];
```

### Verification
The API response now includes:
```json
"will_info": {
    "has_will": true,
    "last_updated": "2024-06-15T00:00:00+01:00",
    "executor_name": "Sarah Jones (Sister)"
}
```

---

## Testing Summary

### End-to-End Testing Completed
1. User Registration (c.jones@csjones.co)
2. Email Verification
3. Login
4. Onboarding Wizard (all 10 steps)
5. Dashboard - All cards loading correctly
6. Net Worth Module - Properties, Investments, Cash all working
7. Retirement Module - Now works after Risk Profile fix
8. Protection Module - Gap analysis working correctly
9. Estate Planning Module - Now shows correct will status

### Remaining Known Issues
1. **Registration Button** - "Create Account" button not clickable via browser automation tools (workaround: use direct API calls)
2. **Preview Mode Writes** - Preview users cannot save data (by design - intercepted by PreviewWriteInterceptor)

---

## Files Changed

1. `database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php` (NEW)
2. `app/Http/Controllers/Api/Estate/IHTController.php` (MODIFIED)
