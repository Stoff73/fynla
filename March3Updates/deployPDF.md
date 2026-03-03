# Deploy: Estate Plan Print/Save PDF

## Summary

Updated `planPrintMixin.js` to support the rewritten Estate Plan structure. The print output now renders the full estate-specific content (structured executive summary, personal information, IHT calculation table, detailed actions with gifting schedules, and what-if comparison table). Also added repeating page header/footer on every printed page and removed browser default date/URL text from print output.

## Files Changed

| File | Change |
|------|--------|
| `resources/js/components/Plans/Shared/planPrintMixin.js` | Estate print support, page header/footer, browser header removal |

## Upload Steps

1. Build locally:
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. Upload via SiteGround File Manager:
   - `public/build/` directory → `~/www/fynla.org/public_html/public/build/`

3. SSH and clear caches:
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```

## What Changed

### Estate-Specific Print Rendering
- `isEstatePlan()` detection — routes estate plans to dedicated builder methods
- `buildEstateExecutiveSummaryHtml()` — greeting, introduction, key actions table with priority badges
- `buildEstatePersonalInformationHtml()` — 2x2 info grid (Personal Details, Family, Financial Overview, Estate Profile)
- `buildEstateCurrentSituationHtml()` — full IHT calculation table with per-owner assets/liabilities, allowances (with widowed transfers), IHT liability in red, effective rate, supplementary cards (asset breakdown, life cover, charitable giving)
- `buildEstateActionsHtml()` — actions with affordability badges, funding source, PET gifting schedule table, annual gifting grid, step-by-step guidance
- `buildEstateWhatIfHtml()` — side-by-side comparison table recalculated from enabled actions

### Page Header/Footer
- Running page header on every page (small logo + plan title)
- Running footer on every page (disclaimer + prepared by)
- `@page { margin: 0 }` removes browser default date/URL/title from printed pages
- Fixed elements use internal padding for edge spacing

### Non-Estate Plans
- No changes — all new code is behind `isEstatePlan()` guard

## Testing

Test with preview personas:
- **peak_earners** (David & Sarah Mitchell) — married couple, both owners' assets/liabilities
- **widow** (Margaret Thompson) — transferred NRB/RNRB from deceased spouse
- **entrepreneur** (Alex Chen) — single owner, business interests
- Any non-estate plan — verify unchanged output
