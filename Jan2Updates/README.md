# January 2, 2026 Updates

## Changes Made

1. **Spouse_Creation_Fix.md** - Spouse creation in onboarding was failing:
   - Root cause: `SpousePermission::updateOrCreate()` used wrong column names
   - Code tried: `can_view_data`, `can_edit_data`, `permission_granted_at`
   - Table actually has: `status`, `requested_at`, `responded_at`
   - Fixed in `FamilyMembersController.php` and `OnboardingService.php`
   - Also improved error messages in `FamilyInfoStep.vue`

2. **Registration_Flow_Fix.md** - Email verification registration flow bugs:
   - Emails not being sent (SMTP auth issue)
   - Modal disappearing on outside click
   - Timer too short (removed timer entirely)
   - "Email already taken" after cancelled registration
   - Solution: Pending registration pattern with `pending_registrations` table

3. **Cache_Tagging_Fixes.md** - Cache tagging compatibility fix for SiteGround:
   - Protection Plan error affecting ALL demo personas
   - Estate Plan error affecting Widow persona
   - BaseAgent.php modified to detect cache driver capabilities
   - ProtectionAgent.php and EstateAgent.php invalidateCache methods fixed
   - Graceful fallback for file-based cache drivers

4. **Deployment_Fixes.md** - Documentation of all deployment-related fixes:
   - SSH connection details updated
   - Server path structure corrected (`~/www/fynla.org/public_html/`)
   - Dual .htaccess setup documented
   - DirectoryMatch fix for shared hosting
   - Package naming consistency

5. **Comprehensive_Module_Testing.md** - Testing checklist for all modules

## Key Fixes

### Spouse Creation (Latest)
If spouse creation fails in onboarding, the fix is in `FamilyMembersController.php` and `OnboardingService.php` - update SpousePermission columns.

### DirectoryMatch 500 Error
DirectoryMatch causes 500 error on SiteGround shared hosting. The `deploy/fynla-org/.htaccess` has been fixed to use `RewriteRule` instead.

If you have an old deployment, run:
```bash
sed -i '/<DirectoryMatch/,/<\/DirectoryMatch>/d' ~/www/fynla.org/public_html/public/.htaccess
```
