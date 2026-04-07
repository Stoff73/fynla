# Deploy Guide — Estate Planning Redesign

**Date**: 7 April 2026
**Branch**: `estateDash` (12 commits, from `main`)

---

## 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

---

## 2. Upload to production

### Frontend only — no PHP changes

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`

No PHP files changed. No seeders. No migrations.

---

## 3. SSH and clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## 4. Files changed

| File | Change |
|------|--------|
| `resources/js/views/Estate/EstateDashboard.vue` | Removed tab navigation, always renders IHTPlanning directly |
| `resources/js/views/Estate/InheritanceTaxDetail.vue` | **New** — dedicated IHT calculation table view at `/estate/inheritance-tax` |
| `resources/js/components/Estate/IHTPlanning.vue` | 3-col card grid, reordered cards, `tableOnly` prop for detail view |
| `resources/js/components/Estate/EstateLifeEventsImpact.vue` | Simplified — removed summary cards, timeline, icons, projected liability |
| `resources/js/router/index.js` | Added `/estate/inheritance-tax` route |

---

## 5. What changed

### Estate Dashboard — Card Grid Layout

Replaced tab-based layout (IHT / Gifting / Life Policy / Trusts) with a 3-column card grid:

**Row 1**: Inheritance Tax Summary | Will | Power of Attorney
**Row 2**: Charitable Bequest | Life Policy | Gifting

All cards visible at once. No tabs.

### Card Improvements

- **IHT Summary**: Clicks through to `/estate/inheritance-tax` (full calculation table)
- **Power of Attorney**: Shows each LPA type (Property & Financial, Health & Welfare) with status instead of "2 Registered"
- **Life Policy**: Cover needed and recommended inline, shows "Joint Second Death" for married users
- **Gifting**: Shows annual exemption (£3,000), small gift allowance (£250/person), and 7-year Potentially Exempt Transfer availability
- **Charitable Bequest**: Personalised amount (e.g. "Leave £72,878+ to charity to reduce your Inheritance Tax rate?") instead of generic "10%+"

### IHT Calculation Table — Own View

Full IHT calculation table moved to `/estate/inheritance-tax`:
- Back to Estate Planning button
- Full breakdown table (assets, liabilities, allowances, taxable estate, liability)
- Tax allowance cards (Nil Rate Band, Residence Nil Rate Band)
- Works for both married (joint death scenario) and single users

### Life Events Impact — Simplified

- Removed 3 summary cards (incoming/outgoing/net)
- Removed timeline dots and lines
- Removed icons from cards
- Removed projected liability text
- Kept individual event impact cards only (coloured by type)
- Hidden from IHT detail view (already on dashboard)

---

## 6. Post-deploy verification

1. Select David & Sarah Mitchell preview persona
2. Navigate to Estate Planning (`/estate`)
3. Verify: 3-column card grid with 6 cards, no tabs
4. Click IHT Summary card → should navigate to `/estate/inheritance-tax`
5. Verify: Full IHT calculation table renders with data
6. Click "Back to Estate Planning" → returns to card grid
7. Verify: Life Events Impact shows individual event cards only (no summary, no timeline)
8. Verify: Power of Attorney card shows "Property & Financial — Registered" and "Health & Welfare — Registered"
9. Verify: Charitable Bequest shows personalised amount
