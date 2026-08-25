# Deploy Guide — Fyn Chat Bug Fixes (PR #206)

**DEPLOYED** 9 April 2026

**Date:** 9 April 2026
**PR:** #206 (`fynBugs` branch, merged to main)
**Commit:** `38bda00`

---

## Summary

6 bugs fixed from user report (Azlan, user 551):
- Multiple form fills now queue sequentially (was only saving last entry)
- Chat scroll fixed — no more jumping to top or huge gap at bottom
- Expenditure tool routing clarified (food, transport, etc. → set_expenditure)
- Mortgage form fill no longer stops partway (missing field + null stripping fixed)

---

## Files to Upload

### Frontend Build (required)

```
public/build/ --> ~/www/fynla.org/public_html/public/build/
```

### PHP Files (2)

```
app/Agents/CoordinatingAgent.php
app/Services/AI/XaiToolDefinitions.php
```

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear
```

---

## No Migrations

No database changes.

---

## Notes

- The fill queue fix means Fyn can now add multiple investments/policies/dependants in a single response — each fills sequentially after the previous one saves
- Mortgage form fill now defaults `interest_rate` to 4.5% and `remaining_term_months` to 300 (25 years) when the user doesn't provide them
- Chat scroll uses `scrollIntoView` instead of manual offset calculation — more reliable across different viewport sizes
