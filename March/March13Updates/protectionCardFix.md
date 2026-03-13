# Protection Dashboard Card Fix — 13 March 2026

## Problem

Protection card on mobile dashboard showed "£0" because `metric_type` was `currency` using `total_coverage` which was 0 (coverage calculation doesn't match policy existence).

## Fix

Changed the card to show policy count instead:

### Backend (`MobileDashboardAggregator.php`)
- Added `policy_count` field by iterating over `$data['policies']` (which contains sub-arrays for life_insurance, critical_illness, income_protection, etc.) and summing counts
- Uses `is_countable()` guard for safety

### Frontend (`mobileDashboard.js`)
- Changed `metric_type` from `'currency'` to `'text'`
- Changed `metric_value` to `"X policies"` / `"X policy"` (singular/plural)
- Added "Policies" row to details array

## Deployment Note

Requires uploading `app/Services/Mobile/MobileDashboardAggregator.php` to production — the iOS app alone won't fix this since the policy count comes from the server API.
