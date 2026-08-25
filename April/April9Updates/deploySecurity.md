# Deploy Guide — Security Audit Remediation

**DEPLOYED** 9 April 2026

**Date:** 9 April 2026
**Branch:** `security`
**PR:** #207

---

## Summary

8 security audit findings addressed. No critical or high severity issues found in the audit. Fixes: NI number mass assignment, security comments, MFA audit logging, storage utility warnings.

---

## Files to Upload

### PHP Files (3)

```
app/Models/FamilyMember.php
app/Agents/CoordinatingAgent.php
app/Http/Controllers/Api/MFAController.php
```

### No Frontend Build Needed

The only frontend changes are comments in `PublicLayout.vue` and `storage.js` — no behaviour change, no build required.

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```

---

## No Migrations

No database changes.

---

## Notes

- `FamilyMember.national_insurance_number` removed from `$fillable` — the controller already sets it explicitly in all code paths, so no behaviour change
- MFA setup now logs `[MFA] Setup initiated` to the application log
- Full security audit report at `April/April9Updates/securityReview.md`
