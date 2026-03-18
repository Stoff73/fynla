# AI Chat Patch — 18 March 2026

**Status: DEPLOYED TO PRODUCTION — 18 March 2026**

## Issue

AI chat crashes with 500 error: `Class "App\Models\RiskProfile" not found`.

The `PrerequisiteGateService` had a wrong import (`App\Models\RiskProfile` instead of `App\Models\Investment\RiskProfile`). This caused any AI chat message to fail because the service is invoked during chat context building.

## Files to Upload

| File | Path on Server |
|------|---------------|
| `app/Services/PrerequisiteGateService.php` | `~/www/fynla.org/public_html/app/Services/PrerequisiteGateService.php` |

## Rebuild Needed

No. This is a PHP-only change — no frontend rebuild required.

## Post-Upload Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
composer dump-autoload
```
