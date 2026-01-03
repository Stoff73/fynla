# January 3, 2026 Updates

## Files in this directory

### BugFixes_Jan3.md
Documents two bugs found during end-to-end testing:
- **Bug #3 (Critical)**: Risk Profile not persisting - Fixed by making database columns nullable
- **Bug #4 (Medium)**: Estate Planning will status not syncing - Fixed by adding will_info to IHT API response

### Production_Hot_File_Fix.md
Documentation about the `public/hot` file issue that can cause blank pages in production.

## Summary of Changes

| File | Change Type | Description |
|------|-------------|-------------|
| `database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php` | NEW | Makes optional risk profile fields nullable |
| `app/Http/Controllers/Api/Estate/IHTController.php` | MODIFIED | Adds will_info to IHT calculation response |

## Deployment Steps

```bash
# Run the new migration
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear
```
