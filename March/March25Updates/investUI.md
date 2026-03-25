# Deploy: Investment UI Consolidation

**Branch:** `investmentUI`
**Date:** 25 March 2026
**Status:** DEPLOYED

## Summary

Consolidated two conflicting investment detail views into one. InvestmentProjections.vue is now the single per-account detail view with a card-based layout. InvestmentDetailInline.vue is retired.

## Changes

### Modified Files

| File | Change |
|------|--------|
| `resources/js/components/NetWorth/InvestmentProjections.vue` | Major rewrite: per-account Monte Carlo, header card with edit/delete, holdings donut, fees card, diversification insights, rebalancing status, tax treatment, drill-down navigation |
| `resources/js/components/NetWorth/InvestmentList.vue` | Swapped `InvestmentDetailInline` import/usage to `InvestmentProjections` (3 references) |

### Removed Files

| File | Reason |
|------|--------|
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Retired — functionality consolidated into InvestmentProjections.vue |

## Deploy Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to production via SiteGround File Manager

Upload the following to `~/www/fynla.org/public_html/`:

```
public/build/                    (entire directory — replace existing)
```

No PHP files changed. No backend changes. No migrations. Frontend-only deploy.

### 3. Clear caches on server

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Rollback

If issues arise, re-upload the previous `public/build/` directory from the last known-good build.

## Testing Checklist

- [ ] Navigate to Investments, click an ISA account — header card shows correct metrics
- [ ] Monte Carlo chart loads for the individual account (not portfolio total)
- [ ] Holdings donut shows per-account allocation
- [ ] Fees card shows per-account breakdown
- [ ] Diversification insights load per-account
- [ ] Rebalancing status shows per-account drift
- [ ] Tax Treatment card appears below Monte Carlo (left column)
- [ ] Click each card — drill-down loads correctly
- [ ] Back button returns to card overview, then to investment list
- [ ] Test with joint account (ownership % shown)
- [ ] Test with Employee Share Scheme account (specialized view)
- [ ] Test with Private Investment account (specialized view)
- [ ] Responsive: tablet (1024px) and mobile (768px)
