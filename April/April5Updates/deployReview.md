# Deploy Guide — Tech Debt Quick Wins (PR #190)

**Date:** 2026-04-05
**PR:** [#190](https://github.com/Stoff73/fynla/pull/190) — merged to main as `9b02fd0`
**Scope:** 38 tech debt fixes from full codebase code review

---

## Risk Assessment

| Area | Risk | Notes |
|---|---|---|
| Tax hardcodes removed | LOW | Values now pulled from TaxConfigService — DB-seeded config must exist. Validate after deploy. |
| Auditable trait on 17 models | LOW | Additive trait, triggers on create/update/delete. Will start creating new audit log entries. |
| HasJointOwnership on JointAccountLog | LOW | Adds query scopes only — no behaviour change unless callers use the new scopes. |
| PreviewWriteInterceptor | LOW | Added `api/bug-report` to EXCLUDED_ROUTES — preview users can now submit bug reports. |
| Vuex store dead code removal | MEDIUM | Removed `fetchRecommendations` from estate/protection/savings stores, `selectedMortgage` from netWorth, `riskLevels` from goals, `hasEdits`/`editCount` from preview. Browser-test affected components. |
| Vue component cleanup | LOW | Mixin replacements, hex→@apply, acronym expansion. Visual only. |
| investmentService.js duplicate method deleted | MEDIUM | Removed duplicate `analyzeAssetLocation`. Keep parameterised version — callers should work fine. |

---

## Files to Upload

### PHP Backend (25 files)

**Models (19):**
```
app/Models/CashAccount.php
app/Models/Document.php
app/Models/Estate/Asset.php
app/Models/Estate/Bequest.php
app/Models/Estate/Gift.php
app/Models/Estate/IHTCalculation.php
app/Models/Estate/IHTProfile.php
app/Models/Estate/LastingPowerOfAttorney.php
app/Models/Estate/Liability.php
app/Models/Estate/LpaAttorney.php
app/Models/Estate/LpaNotificationPerson.php
app/Models/Estate/Trust.php
app/Models/Estate/Will.php
app/Models/Estate/WillDocument.php
app/Models/JointAccountLog.php
app/Models/PersonalAccount.php
app/Models/Subscription.php
app/Models/UserConsent.php
```

**Services (6):**
```
app/Services/Coordination/CrossModuleStrategyService.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/IHTStrategyGeneratorService.php
app/Services/Estate/PersonalizedGiftingStrategyService.php
app/Services/Investment/ContributionEstimatorService.php
app/Services/Investment/ScenarioService.php
```

**Middleware (1):**
```
app/Http/Middleware/PreviewWriteInterceptor.php
```

### Frontend (26 Vue/JS files — rebuild required)

Frontend changes span: Dashboard, Estate, Guidance, Investment, Journey, Onboarding, Preview, Shared components; AdminPanel, public pages; investmentService; 6 Vuex stores.

**Build required:** `./deploy/fynla-org/build.sh` then upload entire `public/build/` directory.

### No changes to:
- `composer.json` / `composer.lock` — no composer install needed
- `routes/` — no route cache clear beyond normal
- `config/` — no config changes
- `database/migrations/` — no migrations to run

---

## Deploy Steps

1. **Build frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload via rsync (or SiteGround File Manager):**
   ```bash
   # Frontend build
   rsync -avz --delete -e "ssh -p 18765" public/build/ \
     u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/public/build/

   # PHP files (upload the 25 files above via File Manager, OR rsync individual files)
   ```

3. **SSH to server and clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```

---

## Post-Deploy Verification

- [ ] Load fynla.org — homepage renders
- [ ] Log in as preview persona (young_family) — dashboard loads
- [ ] Check Estate IHT calculations still work (IHT rate now from TaxConfigService)
- [ ] Check retirement/investment strategy pages render
- [ ] Verify PersonaSelectionModal opens and persona cards display correctly (hex→@apply fix)
- [ ] Verify ProfileCompletenessAlert shows qualitative label (not numeric %)
- [ ] Test bug report submission as a preview user — should succeed
- [ ] Spot-check InheritanceTaxExplainedPage — "IHT" expanded to "Inheritance Tax"
- [ ] Edit an Estate asset as real user — verify new audit log entry created
- [ ] Clear browser cache, test incognito — no MIME errors

---

## Rollback Plan

If issues found after deploy:
```bash
# Revert commit on main
git revert 9b02fd0 -m 1
git push origin main

# Rebuild and redeploy frontend
./deploy/fynla-org/build.sh
rsync ... (as above)
```

The 18 model trait additions are backward-compatible (additive only). The main risk areas are store/service dead code removal — if a Vue component was calling one of the deleted store actions via string dispatch (`dispatch('estate/fetchRecommendations')`), it would fail silently at runtime.
